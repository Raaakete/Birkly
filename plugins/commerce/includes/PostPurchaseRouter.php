<?php
/**
 * Post-purchase router — idempotent fulfillment by type (P6.5, P17-M5 audit).
 *
 * Automation triggers fired at most once per order line (key = order_id:line_id):
 * - commerce_physical_fulfilled — ship lines pushed to fulfillment adapter
 * - commerce_digital_delivered — deliver lines receive download token
 * - commerce_event_registered — register lines create attendee record
 * - commerce_membership_started — recurring none-fulfillment purchases
 */

class PostPurchaseRouter
{
    private string $stateFile;
    private EventRegistrationStore $registrations;
    private DigitalDeliveryStore $digital;
    private PurchaseMappingService $mappings;

    public function __construct(
        ?string $dataDir = null,
        ?EventRegistrationStore $registrations = null,
        ?DigitalDeliveryStore $digital = null,
        ?PurchaseMappingService $mappings = null
    ) {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->stateFile = $dir . '/fulfillment_state.json';
        if (!is_file($this->stateFile)) {
            file_put_contents($this->stateFile, json_encode(['processed' => []], JSON_PRETTY_PRINT));
        }
        $this->registrations = $registrations ?? new EventRegistrationStore($dir);
        $this->digital = $digital ?? new DigitalDeliveryStore($dir);
        $this->mappings = $mappings ?? new PurchaseMappingService($dir);
    }

    public function route(array $order): array
    {
        $results = [];
        foreach ($order['lines'] ?? [] as $idx => $line) {
            $orderId = $order['id'] ?? '';
            $lineId = $line['line_id'] ?? ($orderId . ':' . $idx);
            $key = $orderId . ':' . $lineId;

            if ($this->isProcessed($key)) {
                $results[] = ['line_id' => $lineId, 'skipped' => true];
                continue;
            }

            if (!$this->claimProcessing($key)) {
                $results[] = ['line_id' => $lineId, 'skipped' => true];
                continue;
            }

            if (!empty($line['is_bundle']) && !empty($line['components'])) {
                $expanded = $this->routeBundleLine($order, $line, $lineId);
                $this->markProcessed($key, 'bundle', ['ok' => true, 'expanded' => $expanded]);
                $results[] = ['line_id' => $lineId, 'fulfillment' => 'bundle', 'ok' => true, 'components' => $expanded];
                continue;
            }

            $fulfillment = $line['fulfillment'] ?? 'ship';
            $result = ['line_id' => $lineId, 'fulfillment' => $fulfillment];

            switch ($fulfillment) {
                case 'ship':
                    $result = array_merge($result, $this->handleShip($order, $line));
                    break;
                case 'deliver':
                    $result = array_merge($result, $this->handleDeliver($order, $line, $idx));
                    break;
                case 'register':
                    $result = array_merge($result, $this->handleRegister($order, $line, $idx));
                    break;
                case 'none':
                    $result = array_merge($result, $this->handleNone($order, $line));
                    break;
                default:
                    $result['ok'] = false;
                    $result['error'] = 'Unknown fulfillment: ' . $fulfillment;
            }

            $this->markProcessed($key, $fulfillment, $result);
            $results[] = $result;
        }

        return $results;
    }

    public function isProcessed(string $key): bool
    {
        $data = $this->loadState();
        $entry = $data['processed'][$key] ?? null;

        return is_array($entry) && (($entry['status'] ?? 'done') === 'done');
    }

    private function claimProcessing(string $key): bool
    {
        $data = $this->loadState();
        $entry = $data['processed'][$key] ?? null;
        if (is_array($entry) && in_array($entry['status'] ?? 'done', ['done', 'processing'], true)) {
            return ($entry['status'] ?? '') === 'processing' ? false : false;
        }
        if (is_array($entry) && ($entry['status'] ?? '') === 'done') {
            return false;
        }
        $data['processed'][$key] = [
            'status' => 'processing',
            'fulfillment' => 'pending',
            'at' => date('c'),
        ];
        $this->writeState($data);

        return true;
    }

    private function handleShip(array $order, array $line): array
    {
        $orderId = $order['id'] ?? '';
        $lineId = $line['line_id'] ?? '';
        $existing = $this->getLineFulfillmentState($orderId, $lineId);
        if (is_array($existing) && ($existing['status'] ?? '') === 'done') {
            return ['ok' => true, 'skipped' => true, 'adapter' => $existing['result']['adapter'] ?? null];
        }

        $adapter = $this->resolvePhysicalAdapter();
        if (!$adapter || !$adapter->isConfigured()) {
            return ['ok' => false, 'error' => 'Physical fulfillment adapter not configured'];
        }

        $shipOrder = $order;
        $shipOrder['lines'] = [$line];
        if (!empty($order['shipping_address'])) {
            $shipOrder['shipping_address'] = $order['shipping_address'];
        }

        $push = $adapter->pushOrder($shipOrder);
        $this->fireAutomation('commerce_physical_fulfilled', [
            'order_id' => $orderId,
            'line_id' => $lineId,
            'entry_id' => $line['entry_id'] ?? '',
        ]);

        return ['ok' => $push['ok'] ?? false, 'adapter' => $adapter->getId(), 'export_path' => $push['export_path'] ?? null];
    }

    private function handleDeliver(array $order, array $line, int $idx): array
    {
        $token = $this->digital->createToken($order, $line, $idx);
        if (empty($token['created_at']) || ($token['download_count'] ?? 0) === 0) {
            $this->fireAutomation('commerce_digital_delivered', [
                'order_id' => $order['id'] ?? '',
                'line_id' => $line['line_id'] ?? '',
                'token' => $token['token'] ?? '',
                'customer_email' => $order['customer_email'] ?? '',
            ]);
        }

        return ['ok' => true, 'token' => $token['token'] ?? '', 'expires_at' => $token['expires_at'] ?? ''];
    }

    private function handleRegister(array $order, array $line, int $idx): array
    {
        $existing = $this->registrations->getByOrderLine($order['id'] ?? '', $line['line_id'] ?? '');
        $attendee = $order['attendee_name'] ?? null;
        $record = $this->registrations->register($order, $line, $idx, $attendee);
        if ($existing === null) {
            $this->fireAutomation('commerce_event_registered', [
                'order_id' => $order['id'] ?? '',
                'line_id' => $line['line_id'] ?? '',
                'entry_id' => $line['entry_id'] ?? '',
                'attendee_name' => $record['attendee_name'] ?? '',
                'customer_email' => $order['customer_email'] ?? '',
            ]);
        }

        $tierGranted = $this->mappings->applyLineMappings($order, $line);

        return ['ok' => true, 'registration' => $record, 'tier_granted' => $tierGranted];
    }

    private function handleNone(array $order, array $line): array
    {
        $tierGranted = $this->mappings->applyLineMappings($order, $line);

        if (($line['billing'] ?? '') === 'recurring') {
            $key = ($order['id'] ?? '') . ':' . ($line['line_id'] ?? '');
            if (!$this->isProcessed($key . ':membership_automation')) {
                $this->fireAutomation('commerce_membership_started', [
                    'order_id' => $order['id'] ?? '',
                    'line_id' => $line['line_id'] ?? '',
                    'purchase_option_id' => $line['purchase_option_id'] ?? '',
                    'interval' => $line['interval'] ?? 'month',
                ]);
                $data = $this->loadState();
                $data['processed'][$key . ':membership_automation'] = [
                    'status' => 'done',
                    'fulfillment' => 'membership_automation',
                    'at' => date('c'),
                ];
                $this->writeState($data);
            }
        }

        return ['ok' => true, 'membership' => ($line['billing'] ?? '') === 'recurring', 'tier_granted' => $tierGranted];
    }

    private function routeBundleLine(array $order, array $line, string $bundleLineId): array
    {
        $expanded = [];
        foreach ($line['components'] as $compIdx => $comp) {
            $compLineId = $bundleLineId . ':comp:' . $compIdx;
            $compLine = [
                'line_id' => $compLineId,
                'entry_id' => $comp['entry_id'] ?? '',
                'collection_slug' => $comp['collection'] ?? '',
                'quantity' => (int) ($comp['quantity'] ?? 1),
                'fulfillment' => $comp['fulfillment'] ?? 'ship',
                'commerce' => $comp['commerce'] ?? [],
                'purchase_option_id' => $comp['purchase_option_id'] ?? 'default',
                'billing' => 'one_time',
                'title' => $comp['title'] ?? '',
            ];

            $compKey = ($order['id'] ?? '') . ':' . $compLineId;
            if ($this->isProcessed($compKey)) {
                $expanded[] = ['line_id' => $compLineId, 'skipped' => true];
                continue;
            }
            if (!$this->claimProcessing($compKey)) {
                $expanded[] = ['line_id' => $compLineId, 'skipped' => true];
                continue;
            }

            $fulfillment = $compLine['fulfillment'];
            switch ($fulfillment) {
                case 'ship':
                    $result = array_merge(['line_id' => $compLineId, 'fulfillment' => $fulfillment], $this->handleShip($order, $compLine));
                    break;
                case 'deliver':
                    $result = array_merge(['line_id' => $compLineId, 'fulfillment' => $fulfillment], $this->handleDeliver($order, $compLine, $compIdx));
                    break;
                case 'register':
                    $result = array_merge(['line_id' => $compLineId, 'fulfillment' => $fulfillment], $this->handleRegister($order, $compLine, $compIdx));
                    break;
                case 'none':
                    $result = array_merge(['line_id' => $compLineId, 'fulfillment' => $fulfillment], $this->handleNone($order, $compLine));
                    break;
                default:
                    $result = ['line_id' => $compLineId, 'fulfillment' => $fulfillment, 'ok' => false, 'error' => 'Unknown fulfillment: ' . $fulfillment];
            }

            $this->markProcessed($compKey, $fulfillment, $result);
            $expanded[] = $result;
        }

        return $expanded;
    }

    private function resolvePhysicalAdapter(): ?FulfillmentAdapterInterface
    {
        $connectionsFile = commerce_data_dir() . '/fulfillment_connections.json';
        $adapterId = 'manual';
        if (is_file($connectionsFile)) {
            $connections = json_decode((string) file_get_contents($connectionsFile), true);
            $adapterId = $connections['active_adapter'] ?? 'manual';
        }
        if ($adapterId === 'shipstation') {
            return new ShipStationStubAdapter();
        }
        return new ManualFulfillmentAdapter();
    }

    private function fireAutomation(string $event, array $ctx): void
    {
        if (function_exists('broadcast_event')) {
            broadcast_event($event, $ctx, 'commerce');
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation($event, $ctx);
        }
    }

    private function loadState(): array
    {
        $data = json_decode((string) file_get_contents($this->stateFile), true);
        return is_array($data) ? $data : ['processed' => []];
    }

    private function writeState(array $data): void
    {
        file_put_contents($this->stateFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function markProcessed(string $key, string $fulfillment, array $result): void
    {
        $data = $this->loadState();
        $data['processed'][$key] = [
            'status' => 'done',
            'fulfillment' => $fulfillment,
            'at' => date('c'),
            'result' => $result,
        ];
        $this->writeState($data);
    }

    public function getLineFulfillmentState(string $orderId, string $lineId): ?array
    {
        $data = $this->loadState();
        return $data['processed'][$orderId . ':' . $lineId] ?? null;
    }
}
