<?php
/**
 * Provider registry and auto/fixed resolution.
 */

class PaymentsProviderResolver
{
    /** @var array<string, PaymentProviderInterface> */
    private array $providers = [];

    private PaymentsCredentialStore $store;

    public function __construct(PaymentsCredentialStore $store)
    {
        $this->store = $store;
    }

    public function register(PaymentProviderInterface $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    public function get(string $providerId): ?PaymentProviderInterface
    {
        return $this->providers[$providerId] ?? null;
    }

    /** @return array<string, PaymentProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listAvailableProviders(array $filters = []): array
    {
        $configured = $this->store->listConfiguredProviders();
        $result = [];

        foreach ($this->providers as $id => $provider) {
            $caps = $provider->getCapabilities();
            $isConfigured = in_array($id, $configured, true);

            if (!empty($filters['configured_only']) && !$isConfigured) {
                continue;
            }
            if (!empty($filters['currency'])) {
                $currencies = array_map('strtolower', $caps['currencies'] ?? []);
                if (!in_array(strtolower($filters['currency']), $currencies, true) && !in_array('*', $currencies, true)) {
                    continue;
                }
            }
            if (!empty($filters['country'])) {
                $regions = array_map('strtoupper', $caps['regions'] ?? []);
                if (!in_array(strtoupper($filters['country']), $regions, true) && !in_array('*', $regions, true)) {
                    continue;
                }
            }
            if (!empty($filters['method'])) {
                $methods = array_map('strtolower', $caps['methods'] ?? []);
                if (!in_array(strtolower($filters['method']), $methods, true)) {
                    continue;
                }
            }

            $result[] = [
                'id' => $id,
                'name' => $provider->getName(),
                'configured' => $isConfigured,
                'capabilities' => $caps,
            ];
        }

        return $result;
    }

    /**
     * Resolve provider ID from criteria.
     *
     * @param array<string, mixed> $criteria
     */
    public function resolveProvider(array $criteria): ?string
    {
        $configured = $this->store->listConfiguredProviders();
        if (empty($configured)) {
            return null;
        }

        // 1. Explicit provider
        $explicit = $criteria['provider'] ?? $criteria['provider_id'] ?? null;
        if ($explicit && in_array($explicit, $configured, true)) {
            return $explicit;
        }

        $selectionMode = function_exists('get_plugin_setting')
            ? get_plugin_setting('payments', 'selection_mode', 'auto')
            : 'auto';

        if ($selectionMode === 'fixed') {
            $default = function_exists('get_plugin_setting')
                ? get_plugin_setting('payments', 'default_provider', 'stripe')
                : 'stripe';
            return in_array($default, $configured, true) ? $default : ($configured[0] ?? null);
        }

        // 2. Consumer hint
        $hint = $criteria['provider_hint'] ?? ($criteria['metadata']['provider_hint'] ?? null);
        if ($hint && in_array($hint, $configured, true)) {
            return $hint;
        }

        // 3. Currency rules
        $currency = strtolower($criteria['currency'] ?? '');
        $currencyMap = [
            'jpy' => 'komoju',
            'inr' => 'razorpay',
            'cny' => 'payssion',
        ];
        if ($currency && isset($currencyMap[$currency])) {
            $candidate = $currencyMap[$currency];
            if (in_array($candidate, $configured, true)) {
                return $candidate;
            }
        }

        // 4. Country rules
        $country = strtoupper($criteria['country'] ?? '');
        $countryMap = [
            'CN' => 'payssion',
            'JP' => 'komoju',
            'IN' => 'razorpay',
        ];
        $euCountries = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','NO','CH','GB'];
        if ($country && isset($countryMap[$country])) {
            $candidate = $countryMap[$country];
            if (in_array($candidate, $configured, true)) {
                return $candidate;
            }
        }
        if ($country && in_array($country, $euCountries, true) && in_array('mollie', $configured, true)) {
            return 'mollie';
        }
        if ($country === 'US' && in_array('square', $configured, true)) {
            return 'square';
        }

        // 5. Payment method
        $method = strtolower($criteria['method'] ?? $criteria['payment_method'] ?? '');
        $methodMap = [
            'ideal' => 'mollie',
            'bancontact' => 'mollie',
            'sepa' => 'mollie',
            'alipay' => 'payssion',
            'wechat' => 'payssion',
            'wechat_pay' => 'payssion',
            'konbini' => 'komoju',
            'paypay' => 'komoju',
        ];
        if ($method && isset($methodMap[$method])) {
            $candidate = $methodMap[$method];
            if (in_array($candidate, $configured, true)) {
                return $candidate;
            }
        }

        // 6. Site default
        $default = function_exists('get_plugin_setting')
            ? get_plugin_setting('payments', 'default_provider', 'stripe')
            : 'stripe';
        if (in_array($default, $configured, true)) {
            return $default;
        }

        // 7. First configured
        return $configured[0] ?? null;
    }
}
