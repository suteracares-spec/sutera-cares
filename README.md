# Sutra Cares — website

Landing page for **Sutra Cares**, a community-led medical fund for refugees in Malaysia
(maternal health, safe birth, newborn care and medical emergencies).

Static HTML/CSS — no build step, no framework. Host it anywhere (any shared host,
Netlify, GitHub Pages, or the existing elsystem server). Content is drawn from the
**Sutra Business Plan v1.0 (Aug 2026)** — see `Sutra Business Plan.md` in Downloads or
the Claude artifact of the same name.

## Files

- `index.html` — the whole landing page (one page, anchor navigation)
- `css/styles.css` — styles; brand tokens at the top (`:root`)
- `img/logo.svg` — standalone logo (thread-heart + wordmark) for print/social use
- `img/favicon.svg` — favicon

## Placeholders to fill before launch

| Placeholder | Where | Replace with |
|---|---|---|
| `+60 1X-XXX XXXX` | header + contact | real WhatsApp hotline number (also make it a `https://wa.me/...` link) |
| `hello@sutracares.org` | contact | real email once the domain exists |
| `[Partner organisation account name]` | donate section | fiscal sponsor's bank details after the MOU is signed |
| DuitNow QR box | donate section | real QR image once the gateway/merchant code exists |
| Partner names | partners section | only after MOUs are signed — the page says so deliberately |
| Live ledger box | promises section | link/table once the first cases exist |

## Deliberate choices (from the plan)

- **No partner logos yet** — the plan's trust principle is "we only show what is real".
- **Tax-deductibility stated honestly** (not yet deductible; s.44(6) takes ~2 years).
- **No patient photos** — consent-first media policy; the hero is abstract thread art.
- **Campaigns 100% to patients / Circle funds operations** is repeated on the page —
  it is the fundraising engine's core message.
- Tagline: *"Every mother a safe birth. Every life a chance."* (+ BM version).

## Still to build (later phases, per plan §14)

- BM (Bahasa Malaysia) version / language toggle
- Donation gateway (ToyyibPay or Billplz + DuitNow QR) and campaign pages
- Public ledger page (start as a published Google Sheet embed)
- Volunteer + contact forms (start with mailto/WhatsApp links)
