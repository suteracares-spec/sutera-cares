# Sutera Cares — website

Landing page for **Sutera Cares**, a community-led medical fund for refugees in Malaysia
(maternal health, safe birth, newborn care and medical emergencies).

Static HTML/CSS — no build step, no framework. Host it anywhere (any shared host,
Netlify, GitHub Pages, or the existing elsystem server). Content is drawn from the
**Sutera Business Plan v1.0 (Aug 2026)** — see `Sutera Business Plan.md` in Downloads or
the Claude artifact of the same name.

## Files

- `index.html` — the whole landing page (one page, anchor navigation)
- `css/styles.css` — styles; brand tokens at the top (`:root`)
- `js/i18n.js` — the Malay and Chinese translations, and the language switcher
- `img/logo.svg` — standalone logo (thread-heart + wordmark) for print/social use
- `img/favicon.svg` — favicon

## Languages

The page ships in **English, Bahasa Malaysia and 中文 (Simplified)**, switched in
the browser — one HTML file, no build step, no page reload.

English is not duplicated anywhere: it lives in `index.html` and is read straight
off the page on load. `js/i18n.js` holds only the two translations.

- Every translatable element carries `data-i18n="some.key"` (plus
  `data-i18n-label` for `aria-label`s). Values are HTML, so inline
  `<strong>` / `<em>` / `<a>` / `<br>` are fine inside a translation.
- **To edit copy:** change the English in `index.html`, then update the same key
  under `ms` and `zh` in `js/i18n.js`.
- **To add a string:** give the element a new `data-i18n` key, then add that key
  to both dictionaries. A key missing from a translation falls back to English
  rather than disappearing.
- Language is chosen by `?lang=ms` / `?lang=zh` in the URL, else a saved choice
  (`localStorage`), else the browser's language, else English. The chosen
  language is written back to the URL so a link is shareable.
- Chinese renders in the system CJK font — no webfont is downloaded for it.
  Latin words inside Chinese text still use Nunito Sans / Fraunces.

The switcher is hidden when JavaScript is off; the page then reads as English.

## Placeholders to fill before launch

| Placeholder | Where | Replace with |
|---|---|---|
| `+60 1X-XXX XXXX` | header + contact | real WhatsApp hotline number (also make it a `https://wa.me/...` link) |
| `[Partner organisation account name]` | donate section | fiscal sponsor's bank details after the MOU is signed |
| DuitNow QR box | donate section | real QR image once the gateway/merchant code exists |
| `contact@suteracares.org` | contact | works once the mailbox is created in cPanel |
| Partner names | partners section | only after MOUs are signed — the page says so deliberately |
| Live ledger box | promises section | link/table once the first cases exist |

## Deliberate choices (from the plan)

- **No partner logos yet** — the plan's trust principle is "we only show what is real".
- **Tax-deductibility stated honestly** (not yet deductible; s.44(6) takes ~2 years).
- **No patient photos** — consent-first media policy; the hero is abstract thread art.
- **Campaigns 100% to patients / Circle funds operations** is repeated on the page —
  it is the fundraising engine's core message.
- Tagline: *"Every mother a safe birth. Every life a chance."* (+ BM version).

## Hosting — cPanel at www.suteracares.org

Static files in `public_html/`. No build step, no Node, no database.

```
python make-deploy-zip.py      # writes suteracares-deploy.zip
```

Upload that zip in **cPanel → File Manager → public_html**, then **Extract**.
The zip has no wrapper folder, so the files land directly in `public_html`.
Re-run the script and re-upload after any edit; extracting again overwrites.

Files that go up are listed at the top of `make-deploy-zip.py`. `README.md`,
`.git/`, `.vercel/` and the script itself are deliberately excluded.

### Push to deploy (cPanel Git)

The site is deployed by pushing to a git remote on the server. cPanel reads
`.cpanel.yml` and copies the files into `public_html` on every push.

There is deliberately **no Node.js** in this setup. The site is static HTML,
CSS and SVG — there is nothing to run server-side, and putting a Node app in
front of static files would only add something that can break.

One-time setup:

1. **cPanel → SSH Access → Manage SSH Keys.** Import your public key
   (`~/.ssh/id_ed25519.pub`) and click **Manage → Authorize**. Generate a key
   first with `ssh-keygen -t ed25519` if you don't have one.
2. **cPanel → Git™ Version Control → Create.** Leave "Clone a Repository"
   off, set the path to `/home/<cpaneluser>/repos/sutera-cares` and the name
   to `sutera-cares`. Note the SSH clone URL it shows you.
3. Add it as a remote locally. **Namecheap shared hosting uses SSH port
   21098, not 22** — this is the usual reason the first push fails:

   ```
   git remote add cpanel ssh://<cpaneluser>@<server>.web-hosting.com:21098/home/<cpaneluser>/repos/sutera-cares
   ```

Then, to publish:

```
git push cpanel main
```

cPanel runs the tasks in `.cpanel.yml` and the change is live. Check the
result under **Git Version Control → Manage → Pull or Deploy**.

`origin` (GitHub) and `cpanel` (the live server) are separate remotes — push
to both. Nothing deploys from GitHub on its own.

Deployment copies files over; it never deletes. If a file is removed from the
site, delete it from `public_html` by hand as well.

### Launch order

1. Point the domain's nameservers at the host, and wait for DNS to resolve.
2. Upload and extract the zip.
3. Wait for cPanel → **SSL/TLS Status** to show a valid certificate (AutoSSL
   needs DNS working first).
4. **Only then** uncomment the HTTPS block in `.htaccess` — and, optionally,
   the HSTS header below it. Forcing HTTPS before the certificate exists shows
   every visitor a security warning.

`.htaccess` also sets UTF-8 (needed for the Chinese text), the www canonical
redirect, gzip, cache headers, a custom 404 and basic security headers.

### Still outstanding

- `contact@suteracares.org` needs creating in **cPanel → Email Accounts**
  (a mailbox or a forwarder), or mail to the address on the page bounces.
- No `og:image`, so WhatsApp and Facebook shares show no picture — worth
  adding a 1200×630 PNG before promoting the site.
- The old Vercel deployment still exists; it simply stops receiving traffic
  once DNS points at cPanel.

## Still to build (later phases, per plan §14)

- Donation gateway (ToyyibPay or Billplz + DuitNow QR) and campaign pages
- Public ledger page (start as a published Google Sheet embed)
- Volunteer + contact forms (start with mailto/WhatsApp links)
