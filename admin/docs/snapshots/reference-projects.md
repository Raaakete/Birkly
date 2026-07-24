# Starter packs

Birkly ships starter site templates through **Ops** (Birkly-Marketplace), not in the public CMS repository.

## For site owners

1. Install Birkly from a release zip or clone.
2. In **Admin → Getting started**, pick a starter pack (business, shop, docs, and more).
3. Starter content lands in `content/collections/` and site files in `project/`.

Ops operators manage packs at **Ops → Starter packs**.

## For maintainers

Canonical starter sources live in the private ops repo:

`Birkly-Marketplace/internal/cms/packages/starter-packs/`

Legacy reference-project demos (blog, yoga, coffee, etc.) were moved to:

`Birkly-Marketplace/internal/cms/dev-tree/reference-projects/`

Use `internal/cms/dev-tree/install-reference-project.sh` from a local CMS checkout when you need those older demos for QA.

## Related help

- [Getting started](../00-overview/getting-started.md)
- [Production optimization](production-optimization.md) — optional hydrate and snapshot paths
