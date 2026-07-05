<?php
/**
 * Session-backed commerce cart (v2 — purchase_option_id, parent_id).
 */

class CommerceCartSession
{
    private const SESSION_KEY = 'commerce_cart_items';
    private const AFFILIATE_KEY = 'commerce_affiliate_id';
    private const COUPON_KEY = 'commerce_coupon_code';

    public function getItems(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    public function setItems(array $items): void
    {
        $_SESSION[self::SESSION_KEY] = array_values($items);
    }

    public function addItem(
        string $collectionSlug,
        string $entryId,
        int $quantity = 1,
        ?string $purchaseOptionId = null,
        ?string $parentId = null
    ): array {
        $items = $this->getItems();
        $found = false;
        foreach ($items as &$item) {
            if (($item['collection'] ?? '') === $collectionSlug
                && ($item['entry_id'] ?? '') === $entryId
                && ($item['purchase_option_id'] ?? null) === $purchaseOptionId
            ) {
                $item['quantity'] = max(1, (int) ($item['quantity'] ?? 1) + $quantity);
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $row = [
                'collection' => $collectionSlug,
                'entry_id' => $entryId,
                'quantity' => max(1, $quantity),
            ];
            if ($purchaseOptionId !== null && $purchaseOptionId !== '') {
                $row['purchase_option_id'] = $purchaseOptionId;
            }
            if ($parentId !== null && $parentId !== '') {
                $row['parent_id'] = $parentId;
            }
            $items[] = $row;
        }
        $this->setItems($items);
        return $items;
    }

    public function updateItem(string $collectionSlug, string $entryId, int $quantity, ?string $purchaseOptionId = null): array
    {
        if ($quantity <= 0) {
            return $this->removeItem($collectionSlug, $entryId, $purchaseOptionId);
        }
        $items = $this->getItems();
        foreach ($items as &$item) {
            $match = ($item['collection'] ?? '') === $collectionSlug && ($item['entry_id'] ?? '') === $entryId;
            if ($purchaseOptionId !== null) {
                $match = $match && ($item['purchase_option_id'] ?? null) === $purchaseOptionId;
            }
            if ($match) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        unset($item);
        $this->setItems($items);
        return $items;
    }

    public function removeItem(string $collectionSlug, string $entryId, ?string $purchaseOptionId = null): array
    {
        $items = array_values(array_filter($this->getItems(), function ($item) use ($collectionSlug, $entryId, $purchaseOptionId) {
            if (($item['collection'] ?? '') !== $collectionSlug || ($item['entry_id'] ?? '') !== $entryId) {
                return true;
            }
            if ($purchaseOptionId !== null && ($item['purchase_option_id'] ?? null) !== $purchaseOptionId) {
                return true;
            }
            return false;
        }));
        $this->setItems($items);
        return $items;
    }

    public function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->getItems() as $item) {
            $total += (int) ($item['quantity'] ?? 0);
        }
        return $total;
    }

    public function setAffiliateId(?string $affiliateId): void
    {
        if ($affiliateId === null || $affiliateId === '') {
            unset($_SESSION[self::AFFILIATE_KEY]);
            return;
        }
        $_SESSION[self::AFFILIATE_KEY] = $affiliateId;
    }

    public function getAffiliateId(): ?string
    {
        $id = $_SESSION[self::AFFILIATE_KEY] ?? null;
        return is_string($id) && $id !== '' ? $id : null;
    }

    public function setCouponCode(?string $code): void
    {
        if ($code === null || $code === '') {
            unset($_SESSION[self::COUPON_KEY]);
            return;
        }
        $_SESSION[self::COUPON_KEY] = strtoupper(trim($code));
    }

    public function getCouponCode(): ?string
    {
        $code = $_SESSION[self::COUPON_KEY] ?? null;
        return is_string($code) && $code !== '' ? $code : null;
    }
}
