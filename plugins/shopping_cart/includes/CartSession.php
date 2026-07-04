<?php
/**
 * Session-backed shopping cart.
 */

class ShoppingCartSession
{
    private const SESSION_KEY = 'shopping_cart_items';

    public function getItems(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    public function setItems(array $items): void
    {
        $_SESSION[self::SESSION_KEY] = array_values($items);
    }

    public function addItem(string $productId, int $quantity = 1): array
    {
        $items = $this->getItems();
        $found = false;
        foreach ($items as &$item) {
            if (($item['product_id'] ?? '') === $productId) {
                $item['quantity'] = max(1, (int) ($item['quantity'] ?? 1) + $quantity);
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $items[] = ['product_id' => $productId, 'quantity' => max(1, $quantity)];
        }
        $this->setItems($items);
        return $items;
    }

    public function updateItem(string $productId, int $quantity): array
    {
        $items = $this->getItems();
        if ($quantity <= 0) {
            return $this->removeItem($productId);
        }
        foreach ($items as &$item) {
            if (($item['product_id'] ?? '') === $productId) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        unset($item);
        $this->setItems($items);
        return $items;
    }

    public function removeItem(string $productId): array
    {
        $items = array_values(array_filter($this->getItems(), function ($item) use ($productId) {
            return ($item['product_id'] ?? '') !== $productId;
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
}
