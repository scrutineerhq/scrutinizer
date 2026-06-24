# PANEL.md — Standing Review Panel

> Reusable personas for panel reviews. When we say "run a panel review," these are the standing reviewers. Each brings a distinct bias that stress-tests decisions from a different angle.
>
> **Methodology:** Divergent → convergent. Each panelist votes MUST/SHOULD/COULD/CUT per item. 4+ MUSTs = locked in. 4+ CUTs = eliminated. Middle ground gets debated until convergence (usually 2–3 rounds).

## Panelists

| # | Name | Role | Bias / Focus |
|---|------|------|-------------|
| 1 | **Marta** | Solo WP freelancer, 8 client sites, uses AI daily for debugging | Zero-friction UX. Bails if setup takes >5 min. Represents the majority of WP's long tail — non-technical site owners who need things to just work. |
| 2 | **James** | Agency CTO, 60+ managed sites, junior devs on frontline | Scalable workflows, multi-site concerns, delegation safety. Asks: "Can I trust a junior with this?" and "Does this work across 60 sites?" |
| 3 | **Priya** | Plugin author (WooCommerce extension, 50K+ installs) | Fairness to third-party code. Pushes back if the tool could unfairly blame plugins. Wants attribution accuracy and messaging that distinguishes correlation from causation. |
| 4 | **Tom** | WP hosting support lead (mid-tier managed host) | Support playbook integration. Clean customer handoffs. Cares about what happens when a non-technical user sends a report to support. Would integrate this into his team's triage flow. |
| 5 | **Diane** | Security & privacy reviewer, publishes WP security audits | Trust model, data exposure, credential lifecycle, encryption correctness. Will scrutinize every surface that touches auth, PII, or external data flow. Won't accept hand-waving. |
| 6 | **Carlos** | AI-native developer, Cursor + Claude Code, runs WP for clients | Structured data, deep integration, API ergonomics. Pushes for MCP/tool-use patterns. Wants the machine-readable path to be first-class, not an afterthought. |
| 7 | **Riku** | WP beginner, launched first site 3 months ago | Doesn't know what Application Passwords or OPcache are. Needs hand-holding without condescension. Represents the "I heard AI can help" crowd. If Riku can't use it, the onboarding failed. |
| 8 | **Maya** | wp.org plugin reviewer & WP core contributor | Guidelines compliance, coding standards, WordPress design philosophy. Knows the review queue inside out. Will flag anything that would get rejected or require revision at wp.org submission. |

## When to Use

- **Feature design** — Before building a new milestone or major feature, run the panel against the spec.
- **UX decisions** — When a UI choice could go multiple ways, have the panel vote.
- **Security review** — Diane + Carlos + Maya as a focused sub-panel for auth/crypto/data flows.
- **wp.org readiness** — Maya + Riku + Marta as a sub-panel for submission review.

## How to Reference

In review documents, use the short form: `Marta (solo freelancer)`, `Diane (security)`, etc. Full bios above are context for the reviewer running the panel — the names should be enough in the review itself.
