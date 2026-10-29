# Documentation index

These documents describe the agreed direction, current foundation, and proposed extensions. The root README is the current setup/status reference. **Confirmed** means agreed in conversation; **proposed** means a development default still open to refinement; **pending** means a choice or verification remains outstanding.

Read in this order:

1. [Scope and decisions](decisions.md)
2. [Intake workflow](workflows/intake.md)
3. [Screen specification](screens.md)
4. [Stock workflows](workflows/stock-operations.md)
5. [Data model](data-model.md)
6. [Architecture](architecture.md)
7. [Acceptance scenarios](acceptance.md)
8. [Roadmap](roadmap.md)
9. [Scanning proof and test sheet](scanning-proof.md)
10. [Verification record](verification.md)
11. [Development demo data](demo-data.md)
12. [Planned expected shipments and receipt confirmation](workflows/expected-shipments.md)

The root [AGENTS.md](../AGENTS.md) governs implementation conventions. Update these documents as behaviour is implemented; keep plans and verified implementation clearly distinguished.

- [Integrated receiving scanner](scanning.md) — current application behaviour and build/device checks.

- [Online ISBN metadata and cover selection](book-lookup.md) — current workflow, providers and limitations.

- [Connecting cover providers](provider-setup.md) — Google Books and Brave API setup, result review and fallback links.

- [Lookup provider configuration](lookup-providers.md) — manager modes, metadata/image separation, defaults and adapter extension points.
