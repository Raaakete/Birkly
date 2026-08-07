# Closed alpha — legal and feedback

Terms, privacy, and in-admin feedback for closed-alpha testers (Phase 15).

## Summary

Closed-alpha Birkly installs link to **alpha terms** and **privacy** pages from the admin footer and marketplace site. The **Feedback** widget collects bugs and ideas with category, optional screenshot note, and rate limiting. Use Feedback for product issues; legal pages describe data use during the alpha period.

## Beginner

**Footer links**

In the admin, scroll to the page footer. You should see links such as:

- **Terms of service** (alpha) — acceptable use, no production SLA, software as-is during alpha
- **Privacy policy** — what Birkly collects (account email, feedback content, optional analytics) and retention

The [Birkly website](https://birkly.com) and [marketplace](https://marketplace.birkly.io) host matching legal pages for buyers and providers.

**Submitting feedback**

1. Click **Feedback** in the admin sidebar (or the floating widget).
2. Choose a **category**: Bug, Idea, or Other.
3. Describe what happened or what you want. For bugs, include steps to reproduce.
4. Optionally note that you can attach a screenshot (paste path or describe what you saw).
5. Submit — admins see submissions under **Feedback** in the admin.

Feedback may include page URL, CMS version, and browser user-agent to help reproduce issues. Do not paste passwords or API keys into feedback text.

**Marketplace legal**

Buying or selling on the marketplace is governed by marketplace **Terms**, **Refund policy**, and **Provider Agreement** (for sellers). CMS alpha terms do not replace marketplace checkout terms.

## Advanced Users

**CMS templates:** Alpha legal copy ships as PHP/HTML partials linked from `admin/partials/layout_footer.php`. Update paths in Settings or deploy new CMS build to change URLs.

**Feedback API:** `POST /api/feedback.php` — rate limited per session/IP; stores JSON under `data/feedback/` (not committed). Admin list: Feedback admin page.

**Widget:** `admin/partials/feedback-widget.php` — category select, message body, optional screenshot note field (P15-M4 polish).

**Cross-repo sync:** Birkly-Website legal pages should match CMS footer URLs. Marketplace `views/legal/` pages cover buyer/provider obligations.

**Human review:** Alpha terms are not a substitute for counsel before public launch. See `docs/reviews/P13-P15-phase-closure-checklist.md` legal review item.

## Related

- [Closed alpha guide](closed-alpha-guide.md)
- [Help and onboarding](../01-admin/help-and-onboarding.md)
- [Account](../01-admin/account.md)
- [Marketplace — buyer entitlement and install](../06-extensibility/marketplace-buyer-guide.md)
