# Connecting book and cover providers

## Current providers

| Provider | Purpose | Configuration |
| --- | --- | --- |
| Open Library | Exact ISBN metadata/covers and linked edition covers | Enabled by default |
| Google Books | Additional exact-ISBN metadata and cover choices | GOOGLE_BOOKS_API_KEY |
| Brave Image Search | Wider web-image candidates searched by ISBN | BRAVE_SEARCH_API_KEY |

The receiving page shows whether each optional provider is configured. A missing key is not reported as a provider outage. Google Books is off by default and runs only after Search Google Books is explicitly chosen for the current ISBN; Brave runs only after Search web images is explicitly chosen. It is not the same service as Google Images and does not guarantee identical results.

## Local setup

1. Obtain your own [Google Books API key](https://developers.google.com/books/docs/v1/using#APIKey). Enable Books API in your Google Cloud project and restrict the key to that API where appropriate.
2. Obtain a [Brave Search API key](https://api-dashboard.search.brave.com/app/keys) for a plan supporting image search and your intended display/storage usage. Review the provider's current plan before enabling billing; the app does not create subscriptions or purchase credits.
3. Add the keys to the ignored root .env file, using these exact names:

```dotenv
GOOGLE_BOOKS_API_KEY=your-google-books-key
BRAVE_SEARCH_API_KEY=your-brave-search-key
```

4. Run docker compose exec app php artisan config:clear. Refresh Receive Delivery after saving your current work. Scan the ISBN, then choose Search Google Books when needed. Search web images is a separate Brave request.

Keep real keys out of chat, Git, screenshots and documentation. They are read from Laravel configuration and sent server-side to the provider. No credentials are exposed in provider status labels or result JSON. Tests use fake keys only.

## Cover selection

Exact ISBN covers, linked editions and web-image results have separate groups. Web results may be another edition, a different book or an unrelated image even when the search query is an ISBN. Check the image and its source page against the physical copy. Select a candidate and explicitly Save catalogue. Web selection never applies metadata or changes Product.isbn. ProductImage stores source=Brave Image Search, match_type=web, original external image URL and source page URL; edition_isbn remains null because web search has not verified it.

Web thumbnails load from Brave's thumbnail host. Selected images remain external URLs; provider image files are not downloaded by the server. Use original publisher/source permissions appropriate for subsequent storefront use. Search results do not grant image reuse rights.

Open Google Images and Search Amazon links provide manual discovery without API keys. They open separately using the current ISBN. They do not scrape either website or automatically import its images. Own cover uploads remain available.

## Reliability and limits

Brave requests have a five-second connection timeout, ten-second total timeout and a shared one-request-per-second limiter. Each uncached search asks for twenty results and exposes at most twelve unique usable image URLs. Successful results are cached six hours; empty results fifteen minutes. Configuration/auth errors, rate limits and failed providers show different messages. There is no automatic retry for Brave to avoid repeating potentially billable calls.

Search results must have a public HTTPS source/image URL and an HTTPS thumbnail from imgs.search.brave.com. Reject IP addresses, local hostnames, credentials, explicit ports and unsafe schemes. API-key/token query parameters are removed. The server never fetches arbitrary result images. Direct selection accepts only candidate keys from the current ISBN's cache, with no additional paid search. If cache expires, search again before selecting. Late results are ignored when changing ISBNs; failures retain earlier choices.

## Verification boundary

Provider parsing, Google/Open Library merging, caching, credentials, permission checks, trusted candidate selection, provenance and late-result behavior have automated coverage. Neither Google Books nor Brave has a live key configured in this checkout at implementation time. Live result quality and account quota require those keys and an actual search.

References: [Google Books API](https://developers.google.com/books/docs/v1/using), [Brave Image Search API](https://api-dashboard.search.brave.com/api-reference/images/image_search).

## Google Books without Brave

A Google Books key is enough for additional book cover searches. Choose Search Google Books first, then use Find more covers for Google title/author candidates; Search web images (Brave) remains optional and requires its own key. Google candidates found by title are not proof of the scanned edition, so compare the title, author and image with the actual book.

## Quota-saving default

Google Books being configured does not activate automatic searches. Click Search Google Books for each book that needs it; normal scanning and default Find more covers use Open Library. The per-book choice resets on the next book. Cached results and cache-only selection reduce repeat requests.

## Central mode settings

Keys establish availability; Lookup Providers controls actual usage. Managers set book-information and image modes independently. Defaults remain automatic Open Library and manual Google/Brave. A manager can explicitly choose automatic or disabled. The key setup commands do not change those modes. See [configuration](lookup-providers.md).
