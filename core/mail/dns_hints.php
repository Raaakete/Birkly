<?php
/**
 * DNS record hints for email providers (Phase 65).
 *
 * Provides recommended SPF, DKIM, DMARC and MX-style CNAME records for
 * the ESPs supported by Birkly. These are hints only; always verify with
 * the provider's current documentation before updating DNS.
 */

declare(strict_types=1);

function birkly_mail_dns_hints(string $provider, string $domain): array
{
    $domain = strtolower(trim($domain));
    $provider = strtolower(trim($provider));

    $commonDmarc = "v=DMARC1; p=quarantine; rua=mailto:dmarc@{$domain}; pct=100; adkim=r; aspf=r;";

    switch ($provider) {
        case 'resend':
            return [
                'provider' => 'resend',
                'domain' => $domain,
                'records' => [
                    ['type' => 'TXT', 'host' => $domain, 'value' => 'v=spf1 include:pm.mtasv.net ~all', 'purpose' => 'SPF'],
                    ['type' => 'CNAME', 'host' => 'dkim._domainkey.' . $domain, 'value' => 'dkim.pm.mtasv.net.', 'purpose' => 'DKIM (verify exact selector in Resend dashboard)'],
                    ['type' => 'TXT', 'host' => '_dmarc.' . $domain, 'value' => $commonDmarc, 'purpose' => 'DMARC'],
                ],
                'note' => 'Resend uses Postmark infrastructure. Verify DKIM selector and CNAME target in your Resend dashboard before saving DNS.',
            ];

        case 'postmark':
            return [
                'provider' => 'postmark',
                'domain' => $domain,
                'records' => [
                    ['type' => 'TXT', 'host' => $domain, 'value' => 'v=spf1 include:spf.mtasv.net ~all', 'purpose' => 'SPF'],
                    ['type' => 'CNAME', 'host' => 'dkim._domainkey.' . $domain, 'value' => 'dkim.mtasv.net.', 'purpose' => 'DKIM (verify selector in Postmark)'],
                    ['type' => 'TXT', 'host' => '_dmarc.' . $domain, 'value' => $commonDmarc, 'purpose' => 'DMARC'],
                ],
                'note' => 'Postmark supports custom return-path records for bounce handling; add a CNAME for pm-bounces if enabled.',
            ];

        case 'ses':
            return [
                'provider' => 'ses',
                'domain' => $domain,
                'records' => [
                    ['type' => 'TXT', 'host' => $domain, 'value' => 'v=spf1 include:amazonses.com ~all', 'purpose' => 'SPF'],
                    ['type' => 'CNAME', 'host' => '[selector]._domainkey.' . $domain, 'value' => '[selector].dkim.amazonses.com.', 'purpose' => 'DKIM (replace [selector] with SES provided selector)'],
                    ['type' => 'TXT', 'host' => '_dmarc.' . $domain, 'value' => $commonDmarc, 'purpose' => 'DMARC'],
                    ['type' => 'MX', 'host' => '[mail-from-subdomain].' . $domain, 'value' => 'feedback-smtp.[region].amazonses.com.', 'purpose' => 'Custom MAIL FROM / bounce domain'],
                ],
                'note' => 'Use the exact DKIM selector, AWS region, and MAIL FROM subdomain shown in the SES console.',
            ];

        case 'mailgun':
            return [
                'provider' => 'mailgun',
                'domain' => $domain,
                'records' => [
                    ['type' => 'TXT', 'host' => $domain, 'value' => 'v=spf1 include:mailgun.org ~all', 'purpose' => 'SPF'],
                    ['type' => 'TXT', 'host' => 'smtp._domainkey.' . $domain, 'value' => 'k=rsa; p=[Mailgun DKIM public key]', 'purpose' => 'DKIM'],
                    ['type' => 'CNAME', 'host' => 'email.' . $domain, 'value' => 'mailgun.org.', 'purpose' => 'Tracking / opens / clicks'],
                    ['type' => 'TXT', 'host' => '_dmarc.' . $domain, 'value' => $commonDmarc, 'purpose' => 'DMARC'],
                ],
                'note' => 'Mailgun DKIM is a TXT record containing the public key from the Mailgun domain settings.',
            ];

        case 'smtp':
        case 'mail':
        case 'log':
            return [
                'provider' => $provider,
                'domain' => $domain,
                'records' => [
                    ['type' => 'TXT', 'host' => $domain, 'value' => 'v=spf1 a mx ~all', 'purpose' => 'SPF'],
                    ['type' => 'TXT', 'host' => '_dmarc.' . $domain, 'value' => $commonDmarc, 'purpose' => 'DMARC'],
                ],
                'note' => 'Self-hosted or local transports: configure SPF and DMARC for your own outbound IP/MX.',
            ];

        default:
            return [
                'provider' => $provider,
                'domain' => $domain,
                'records' => [
                    ['type' => 'TXT', 'host' => $domain, 'value' => 'v=spf1 ~all', 'purpose' => 'SPF'],
                    ['type' => 'TXT', 'host' => '_dmarc.' . $domain, 'value' => $commonDmarc, 'purpose' => 'DMARC'],
                ],
                'note' => 'Generic hints; consult your provider for exact DKIM and return-path records.',
            ];
    }
}
