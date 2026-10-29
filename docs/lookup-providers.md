# Lookup providers configuration

Managers configure services under Configuration -> Lookup Providers, or follow Configure lookup providers from Receive Delivery. Configuration separates book information (title/authors/description/publication data) from cover/image lookup. Each supported capability has Automatic, Manual/on request or Disabled mode.

## Defaults

| Provider | Kind | Book information | Covers/images | Credentials |
| --- | --- | --- | --- | --- |
| Open Library | Book catalogue | Automatic | Automatic | None |
| Google Books | Book catalogue | Manual | Manual | Local GOOGLE_BOOKS_API_KEY |
| Brave Image Search | Web images | Unsupported/disabled | Manual | Local BRAVE_SEARCH_API_KEY |

API keys stay in the ignored environment file. This screen shows key configured/missing without exposing secrets. It does not create accounts, activate billing or store copied keys in the database.

Automatic capabilities run after ISBN lookup. Manual uses Search Open Library, Search Google Books or Search web images (Brave). Disabled prevents provider calls and excludes previously cached results. Turning automatic Google/Brave on is an explicit manager choice that can consume provider quota; defaults preserve the user's manual Google preference. Modes are independent, although a provider API may return book information and images together in one response. Only enabled capability data is shown, and a cached provider response can serve the other enabled capability without another call.

Changes apply to subsequent requests and refreshes. Settings signatures isolate visible results/candidates after mode changes; a disabled provider cannot be recovered by guessing an old cached candidate key. Automatic Brave requests are marked separately so a page with older settings cannot continue automatic searches after the provider switches to manual. General BOOK_LOOKUP_ENABLED remains an emergency global switch.

## Permissions and audit

Only staff managers may list, view or edit settings, including direct Livewire requests. The saving actor and timestamp are recorded. Existing registered providers cannot be deleted or renamed through this screen. Unsupported capabilities cannot be enabled, even with a direct model/form request.

## Adding another service

Implement a provider adapter, register its capabilities/type/credential configuration in LookupProvider definitions, and add its default settings row in an additive migration. Add authorization, timeout, cache, failure-isolation and quota tests, then expose its manual/automatic UI path. A book-metadata API, image-search API or future site-specific scraping adapter are distinct service kinds; they do not all have to provide book information and images. No scraping service is implemented or enabled by these settings, and arbitrary URLs cannot be entered as providers.

See [provider key setup](provider-setup.md) and [book lookup](book-lookup.md).
