<?php
/**
 * Discount and affiliate rule engine.
 */

class DiscountEngine
{
    private string $rulesFile;

    public function __construct(?string $dataDir = null)
    {
        $dir = $dataDir ?? commerce_data_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->rulesFile = $dir . '/discount_rules.json';
        if (!is_file($this->rulesFile)) {
            file_put_contents($this->rulesFile, json_encode(['rules' => []], JSON_PRETTY_PRINT));
        }
    }

    public function loadRules(): array
    {
        $data = json_decode((string) file_get_contents($this->rulesFile), true);
        return is_array($data) ? $data : ['rules' => []];
    }

    public function saveRules(array $data): void
    {
        file_put_contents($this->rulesFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function listRules(): array
    {
        return array_values($this->loadRules()['rules'] ?? []);
    }

    public function saveRule(array $rule): array
    {
        $data = $this->loadRules();
        $id = $rule['id'] ?? ('rule_' . bin2hex(random_bytes(4)));
        $rule['id'] = $id;
        $rule['updated_at'] = date('c');
        $data['rules'][$id] = $rule;
        $this->saveRules($data);
        return $rule;
    }

    public function deleteRule(string $id): bool
    {
        $data = $this->loadRules();
        if (!isset($data['rules'][$id])) {
            return false;
        }
        unset($data['rules'][$id]);
        $this->saveRules($data);
        return true;
    }

    public function applyToLine(array $catalogItem, int $qty, ?string $coupon = null, ?string $affiliateId = null): array
    {
        $baseUnit = (int) ($catalogItem['price_cents'] ?? 0);
        $discountPerUnit = 0;
        $rules = $this->listRules();

        foreach ($rules as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }
            if (!$this->ruleMatchesItem($rule, $catalogItem)) {
                continue;
            }
            $type = $rule['type'] ?? '';
            if ($type === 'coupon') {
                $code = strtoupper(trim((string) ($rule['code'] ?? '')));
                if ($coupon === null || strtoupper($coupon) !== $code) {
                    continue;
                }
            }
            if ($type === 'affiliate') {
                $ref = (string) ($rule['affiliate_id'] ?? '');
                if ($affiliateId === null || $affiliateId !== $ref) {
                    continue;
                }
            }
            $discountPerUnit += $this->computeDiscountPerUnit($rule, $baseUnit);
        }

        $discountPerUnit = min($discountPerUnit, $baseUnit);
        $effectiveUnit = max(0, $baseUnit - $discountPerUnit);

        return [
            'base_unit_cents' => $baseUnit,
            'effective_unit_cents' => $effectiveUnit,
            'discount_cents' => $discountPerUnit * $qty,
        ];
    }

    private function ruleMatchesItem(array $rule, array $item): bool
    {
        $target = $rule['target'] ?? '';
        if ($target === '') {
            return true;
        }
        if (str_starts_with($target, 'entry:')) {
            $parts = explode(':', $target, 3);
            $coll = $parts[1] ?? '';
            $entry = $parts[2] ?? '';
            return ($item['collection_slug'] ?? '') === $coll
                && (($item['entry_id'] ?? '') === $entry || ($item['entry_slug'] ?? '') === $entry);
        }
        if (str_starts_with($target, 'collection:')) {
            $coll = substr($target, strlen('collection:'));
            return ($item['collection_slug'] ?? '') === $coll;
        }
        return true;
    }

    private function computeDiscountPerUnit(array $rule, int $baseUnit): int
    {
        $type = $rule['type'] ?? '';
        if ($type === 'percent_off' || $type === 'coupon' || $type === 'affiliate') {
            $pct = (float) ($rule['percent_off'] ?? 0);
            return (int) round($baseUnit * ($pct / 100));
        }
        if ($type === 'fixed_off') {
            return (int) ($rule['fixed_off_cents'] ?? 0);
        }
        return 0;
    }
}
