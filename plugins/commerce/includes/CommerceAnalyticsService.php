<?php
/** Commerce analytics aggregates (P11-M5) */

class CommerceAnalyticsService
{
    private CommerceOrderStore $orders;

    public function __construct(?CommerceOrderStore $orders = null)
    {
        $this->orders = $orders ?? new CommerceOrderStore();
    }

    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $orders = $this->filterOrders($from, $to);
        $paid = array_values(array_filter($orders, fn ($o) => in_array($o['status'] ?? '', ['paid', 'partially_refunded', 'refunded'], true)));
        $revenue = 0;
        $products = [];
        $affiliates = [];
        foreach ($paid as $order) {
            $total = (int) ($order['total_cents'] ?? 0);
            $refunded = (int) ($order['refunded_cents'] ?? 0);
            $revenue += max(0, $total - $refunded);
            foreach ($order['lines'] ?? [] as $line) {
                $key = ($line['collection_slug'] ?? '') . ':' . ($line['entry_id'] ?? '');
                if (!isset($products[$key])) {
                    $products[$key] = ['key' => $key, 'title' => $line['title'] ?? $key, 'quantity' => 0, 'revenue_cents' => 0];
                }
                $products[$key]['quantity'] += (int) ($line['quantity'] ?? 1);
                $products[$key]['revenue_cents'] += (int) ($line['line_total_cents'] ?? $line['effective_amount_cents'] ?? 0);
            }
            $aff = trim((string) ($order['affiliate_id'] ?? ''));
            if ($aff !== '') {
                if (!isset($affiliates[$aff])) {
                    $affiliates[$aff] = ['affiliate_id' => $aff, 'orders' => 0, 'revenue_cents' => 0];
                }
                $affiliates[$aff]['orders']++;
                $affiliates[$aff]['revenue_cents'] += max(0, $total - $refunded);
            }
        }
        $count = count($paid);
        $top = array_values($products);
        usort($top, fn ($a, $b) => ($b['revenue_cents'] ?? 0) <=> ($a['revenue_cents'] ?? 0));
        $aff = array_values($affiliates);
        usort($aff, fn ($a, $b) => ($b['revenue_cents'] ?? 0) <=> ($a['revenue_cents'] ?? 0));
        return [
            'from' => $from, 'to' => $to, 'order_count' => $count, 'revenue_cents' => $revenue,
            'refunded_cents' => array_sum(array_map(fn ($o) => (int) ($o['refunded_cents'] ?? 0), $paid)),
            'aov_cents' => $count > 0 ? (int) round($revenue / $count) : 0,
            'top_products' => array_slice($top, 0, 10), 'affiliates' => $aff,
        ];
    }

    public function exportOrdersCsv(?string $from = null, ?string $to = null): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['order_id', 'status', 'created_at', 'customer_email', 'currency', 'total_cents', 'refunded_cents', 'affiliate_id']);
        foreach ($this->filterOrders($from, $to) as $o) {
            fputcsv($out, [$o['id'] ?? '', $o['status'] ?? '', $o['created_at'] ?? '', $o['customer_email'] ?? '', $o['currency'] ?? 'eur', (int) ($o['total_cents'] ?? 0), (int) ($o['refunded_cents'] ?? 0), $o['affiliate_id'] ?? '']);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv === false ? '' : $csv;
    }

    public function exportSummaryCsv(?string $from = null, ?string $to = null): string
    {
        $s = $this->getSummary($from, $to);
        $out = fopen('php://temp', 'r+');
        foreach ([['metric', 'value'], ['order_count', $s['order_count']], ['revenue_cents', $s['revenue_cents']], ['refunded_cents', $s['refunded_cents']], ['aov_cents', $s['aov_cents']]] as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv === false ? '' : $csv;
    }

    private function filterOrders(?string $from, ?string $to): array
    {
        $fromTs = $from ? strtotime($from) : null;
        $toTs = $to ? strtotime($to) : null;
        return array_values(array_filter($this->orders->listAll(), function ($order) use ($fromTs, $toTs) {
            $created = strtotime($order['created_at'] ?? '');
            if ($created === false) {
                return false;
            }
            if ($fromTs && $created < $fromTs) {
                return false;
            }
            if ($toTs && $created > $toTs) {
                return false;
            }
            return true;
        }));
    }
}
