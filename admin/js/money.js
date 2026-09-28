/**
 * Birkly.money — one money formatter for the admin (Birch spec §12).
 * Mirrors core/money.php: integer minor units in, never rounded or abbreviated;
 * a symbol only when it names one currency, otherwise the ISO code.
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    const UNAMBIGUOUS = { EUR: '€', GBP: '£', INR: '₹', KRW: '₩', ILS: '₪', TRY: '₺', UAH: '₴', NGN: '₦', PHP: '₱', THB: '฿', VND: '₫' };

    function minorDigits(currency) {
        try {
            return new Intl.NumberFormat('en', { style: 'currency', currency: currency }).resolvedOptions().maximumFractionDigits;
        } catch (e) {
            return 2;
        }
    }

    /**
     * @param {number} minor Integer minor units.
     * @param {string} currency ISO 4217 code.
     * @param {{mixed?: boolean}} [opts] mixed: the view shows more than one currency.
     */
    Birkly.money = function money(minor, currency, opts) {
        const code = String(currency || 'EUR').trim().toUpperCase();
        const digits = minorDigits(code);
        const value = Math.abs(Number(minor) || 0) / Math.pow(10, digits);
        const amount = value.toLocaleString('en', { minimumFractionDigits: digits, maximumFractionDigits: digits });
        const useSymbol = !(opts && opts.mixed) && Object.prototype.hasOwnProperty.call(UNAMBIGUOUS, code);
        const text = useSymbol ? UNAMBIGUOUS[code] + amount : code + '\u00A0' + amount;
        return (Number(minor) < 0 ? '−' : '') + text;
    };
})();
