# Admin docs

**Do not edit product documentation here.**

Human-facing docs are maintained in **[Birkly-Docs](https://github.com/Raaakete/Birkly-Docs)** and published at [docs.birkly.io](https://docs.birkly.io). Admin Help fetches each article from that origin when the topic is opened. This install does not store or serve the article.

`docs/help-manifest.json` is a mirror of the topic list, refreshed by `./scripts/sync-birkly-docs.sh`. It is not the article body.

## Agent / AI docs

Operational agent playbooks remain in `docs/ai/playbooks/` in the CMS repo. They link to Birkly-Docs via `ai_topic`. They are not the owner manual.
