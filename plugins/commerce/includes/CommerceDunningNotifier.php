<?php
/**
 * Optional built-in dunning email notifications (P11-M2).
 */

class CommerceDunningNotifier
{
    public function sendPaymentFailedEmail(array $subscription): bool
    {
        if (!(bool) commerce_get_setting('dunning_email_enabled', false)) {
            return false;
        }

        $email = trim((string) ($subscription['customer_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = (string) commerce_get_setting(
            'dunning_email_subject',
            'Action required: subscription payment failed'
        );
        $bodyTemplate = (string) commerce_get_setting(
            'dunning_email_body',
            "Hello,\n\nWe were unable to process your subscription payment. Please update your payment method to avoid interruption.\n\nSubscription: {subscription_id}\nAmount: {amount}\n\nThank you."
        );

        $amount = $this->formatAmount(
            (int) ($subscription['amount_cents'] ?? 0),
            (string) ($subscription['currency'] ?? 'eur')
        );

        $replacements = [
            '{subscription_id}' => (string) ($subscription['id'] ?? ''),
            '{customer_email}' => $email,
            '{amount}' => $amount,
            '{interval}' => (string) ($subscription['interval'] ?? ''),
            '{attempt_count}' => (string) (($subscription['dunning']['attempt_count'] ?? 1)),
        ];

        $body = strtr($bodyTemplate, $replacements);
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

        return @mail($email, $subject, $body, $headers);
    }

    private function formatAmount(int $cents, string $currency): string
    {
        $major = number_format($cents / 100, 2);
        return strtoupper($currency) . ' ' . $major;
    }
}
