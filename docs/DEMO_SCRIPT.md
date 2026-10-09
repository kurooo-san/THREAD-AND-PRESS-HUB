# 🎓 Thread & Press Hub — Capstone Defense Demo Script

**Total demo time:** ~15–17 minutes (adjust per panel rules)
**Kailangan:** 2 browser tabs — Tab 1: customer (naka-login), Tab 2: admin

---

## ✅ PRE-DEMO CHECKLIST (gawin bago ang defense day + ulitin 1 oras bago)

- [ ] XAMPP running (Apache + MySQL), site loads
- [ ] **Gemini billing active** — test 1 try-on + 1 AI design + 1 AI product *sa mismong araw* para sigurado
- [ ] Customer account naka-login sa Tab 1; Admin sa Tab 2
- [ ] Camera permission **granted na** sa browser (para walang permission popup sa demo)
- [ ] Maayos ang ilaw sa demo area (para maganda ang try-on result)
- [ ] Empty ang cart ng customer account (malinis na simula)
- [ ] Walang coupon na **DEMO20** pa (gagawin mo ito live sa Step 5) — kung meron na galing sa practice run, i-delete muna sa Admin → Coupons
- [ ] May laman na orders ngayong buwan (para may maipakita ang Sales Report sa Step 6) — i-check sa Admin → Sales Report → "This month"
- [ ] Na-test na ang **Export Excel** sa mismong computer na gagamitin (dapat may Excel o kahit Google Sheets na mabubuksan)
- [ ] **BACKUP:** screen-record ang isang successful run ng buong demo. Kung mag-fail ang internet/AI sa defense, may video ka.
- [ ] Backup ng database (export sa phpMyAdmin) + kopya ng buong folder + `.env`
- [ ] Naka-print o nasa slides ang `docs/QR-system-link.png` (QR ng live Railway site) para ma-scan ng panel at ma-try nila mismo. I-scan muna sa sariling phone para sigurado.
- [ ] Isara ang mga hindi kailangang tabs/apps (para walang notification na sisingit)

---

## 🎬 ANG DEMO FLOW

### 1. Opening — Landing Page (1 min)
**Buksan:** `index.php`

**Sabihin:**
> "Ang Thread & Press Hub po ay hindi lang ordinaryong online clothing store — isa po itong **AI-powered apparel platform** na may limang AI features: Virtual Try-On, AI Stylist, AI Design Generator, AI Product Generation, at AI customer support."

**Gawin:**
- I-scroll sa **AI Virtual Try-On spotlight** (yung dark section na may animated viewfinder) — ituro ang "Powered by Google Gemini AI"
- *Optional flourish:* i-click ang 🌙 toggle — "May full dark mode din po ang buong system" — tapos ibalik (o iwan sa kung ano ang mas maganda sa projector — **light mode ang mas ligtas sa mahinang projector**)

---

### 2. Shop (1–2 min)
**I-click:** Shop

**Gawin:**
- I-hover ang isang product card (para makita ang zoom + quick add)
- Ipakita ang filters (Men/Women/Kids, colors, sizes)
- **Ituro ang isang product na may gold "MADE TO ORDER" badge:**

**Sabihin:**
> "Pansinin niyo po ang badge na ito — babalikan natin 'to mamaya sa admin side. Spoiler po: AI ang gumawa ng produktong 'yan." 😏
*(Nagtatanim ka ng curiosity — babalik ka rito sa Step 6.)*

---

### 3. ⭐ Virtual Try-On + AI Stylist (3–4 min) — ANG BIDA
**I-click:** Try-On

**Gawin (sunud-sunod):**
1. Pindutin **"Camera On"** → *"May privacy-first design po — hindi awtomatikong bumubukas ang camera."*
2. Pindutin ang gender filter (hal. **Men**) → *"Naka-filter po ang catalog at ang AI suggestions ayon sa napili."*
3. Pindutin **"AI Stylist"** → maghintay ~3-5 sec → lalabas ang top 3 suggestions na may dahilan
   > "Tinitingnan po ng AI ang itsura ko — kulay, build, style — at nagmumungkahi ng pinaka-babagay mula sa aktwal naming catalog, may paliwanag pa po kung bakit."
4. Pindutin ang isang suggestion → **5-second countdown** (oras para pumwesto) → mag-ge-generate (~10-15 sec)
   > Habang naghihintay: "Ang frame po ng camera at ang garment photo ay pinoproseso ng Gemini image model — ang API key ay nasa server, hindi po nalalantad sa browser."
5. Paglabas ng resulta: pindutin **"Compare"** → i-drag ang before/after slider 🌟 *(pinaka-malakas na visual moment)*
6. Mabilisang ipakita: **Save look**, **Download** (banggitin ang watermark), **Add to Cart**

**BACKUP kung ayaw ng camera/madilim:** gamitin ang **"Upload Photo"** — maghanda ng magandang full-body photo sa desktop.

---

### 4. Custom Design + AI Design Generator (3 min)
**I-click:** Design

**Gawin:**
1. Sa **AI Design panel**, pindutin ang isang chip (hal. "Kawaii cat") → **Generate Design** → ~5-8 sec → lalabas ang artwork sa canvas
   > "Kahit po hindi marunong mag-drawing ang customer, kaya niyang magpagawa ng design sa AI."
2. **I-drag, i-resize, i-rotate** ang artwork; ipakita ang **Layers panel** (reorder/delete)
3. Magdagdag ng text (pangalan ng panelist? 😄) → ipakita sa **Live 3D Preview** → pindutin **Spin** at **Flip**
4. Ituro ang **Price Estimate** na live nag-a-update
5. **Submit & Proceed to Order** → ipakita ang order summary → **Proceed to Payment** → ipakita na **parehong payment options** ang custom at regular orders: **Pay Online (PayMongo)** o **Cash on Delivery**

---

### 5. Voucher + Checkout — VAT, Discounts, Payment (3 min)

**A. Gumawa at ibigay ang voucher (Tab 2, Admin → Coupons)**
- Code: **DEMO20** · Voucher for: **Discount** · Type: **Percent** · Value: **20** · Max uses: **5** · Valid until: bukas → **Create coupon**
- Sa row ng DEMO20 pindutin ang **Give** → piliin ang demo customer → Gift note (optional) → **Give voucher**
  > "Ang admin po ang nagbibigay ng voucher — sa piling customer o sa lahat. Walang code na ita-type kaya hindi ito maikakalat o mahuhulaan."

**B. Lumabas agad sa customer (Tab 1)**
- Buksan ang **My Vouchers** (user menu) → nandoon na ang DEMO20 (may email din sa customer)
  > "Dynamic po — galing sa database, kaya pagka-give ng admin, nasa wallet na agad."

**C. Gamitin sa checkout**
- **Balik sa cart** (may laman na galing sa try-on Add to Cart) → Proceed to Checkout
- Nakapili na ang **DEMO20** sa voucher section — awtomatikong pinipili ang pinakamalaking matitipid
  - May linya sa summary: **Voucher (DEMO20)**
- Ituro ang breakdown: **Subtotal → Discount (PWD/Senior 20%) → Voucher → VAT 12% → Delivery fee → Total**
  > "Transparent po ang computation. Pwede pong pagsabayin ang PWD/Senior discount at voucher, at ang 12% VAT ay kinukuwenta pagkatapos ng discounts."
- *Optional:* kung may minimum spend ang voucher, ituro ang **"Add ₱… more to unlock"** progress bar
  > "Bawat voucher po ay chine-check ulit sa server — expiry, limit, minimum order, at kung sa customer talaga ito. Kahit baguhin pa sa browser, hindi po ito tatanggapin."

**D. Place order**
- Piliin ang **Cash on Delivery** → **Place Order** (pinakamabilis at walang aasahang internet)
  > "May **Pay Online** din po through PayMongo — GCash, Maya, GrabPay o card. Sa secure page po ng PayMongo nagbabayad, kaya wala kaming hinahawakang card details, at awtomatikong nakukumpirma ang bayad."
- Sa Order Confirmation, ituro ang **Voucher (DEMO20)** line → *"Naka-record din po ito sa invoice at sa confirmation email."*

---

### 6. Admin Side (4 min)
**Lipat sa Tab 2 (Admin)**

**Gawin (sunud-sunod):**
1. **Dashboard** — ipakita ang KPIs + Revenue Trends chart (5 sec lang)
2. **Orders** → buksan ang kaka-place lang na order → ituro ang **Voucher (DEMO20)** line → palitan ang status sa **Confirmed**
   > "Nakikita po ng admin ang bawat discount na ibinigay, at naka-log sa Audit Log ang mga pagbabago."
3. **Coupons** → ituro ang DEMO20: **Used 1 / 5** at ang **Given** na halaga
   > "Awtomatiko pong nabibilang ang gamit. Kapag naabot ang limit, hindi na po ito tatanggapin — kahit sabay pang mag-checkout ang dalawang customer sa huling slot."
   - *Optional:* pindutin ang **Edit** o **Deactivate** para ipakitang buo ang management
4. **Sales Report** (1 min)
   - Pindutin ang **This month** → ituro ang Total sales, Orders, Average order, at ang hati ng **Discounts given** (PWD/Senior vs Vouchers vs Shipping)
   - I-scroll: **Daily sales chart → Top products → By payment method → Orders list**
     > "Ito po ang sagot kung paano malalaman ng may-ari ang benta sa kahit anong petsa — hindi na po kailangang mag-compute nang mano-mano."
   - Pindutin ang **Export Excel** → buksan ang file → ipakita ang 4 na sheet (Summary, Orders, Top Products, Daily Sales)
     > "Naka-format na po ito para sa accounting — may peso formatting, filters, at totals na hindi kasama ang cancelled orders."
   - *Kung kapos sa oras:* **Print** na lang ang ipakita (may sariling report header)
5. **AI Insights** (pindutin ang "AI Insights" button sa admin panel) → i-type: **"magkano discount natin this month?"**
   > "Pati po ang AI assistant ng admin ay alam ang vouchers at discounts — galing sa totoong data, hindi hula."
   - *Follow-up kung may oras:* **"ilang customer na ang nabigyan ng voucher at ilan pa ang hindi nagagamit?"**
6. **⭐ AI Generate Product — ang pang-finale:**
   > "Ito po ang pang-apat naming AI feature. Sir/Ma'am, **anong product po ang gusto niyong idagdag sa store?**"
   - I-type ang sagot ng panelist → **Generate** → ~8-10 sec → lalabas ang draft (photo, name, price, description)
   - > "AI ang nag-draft, pero admin ang nag-a-approve — human oversight po."
   - I-edit ang presyo kung gusto → **Save to Store**
   - **Lipat sa Tab 1 → Shop → refresh** → nandoon na ang product ng panelist, may **"Made to Order" badge!** 🎤⬇️
   - > "At 'yan po ang badge na ipinakita ko kanina — transparent po kami na ang mga AI-concept products ay printed on demand, gaya ng custom orders namin. Ganito rin po ang modelo ng Printify at Teespring."

---

### 7. AI Chatbot (1 min, pang-sara)
**Sa Tab 1:** buksan ang chat widget (💬)

**Pindutin ang quick button na "My Vouchers"** — sasagot ang bot ng mga voucher sa wallet ng customer at kung paano gamitin.
**Ipakita ang handoff:** i-type ang "Gusto kong makausap ang tao" → **Talk to a person** → lilipat sa Live Support kasama ang buong usapan.
**O i-type:** "How does the virtual try-on work?" o "Is there VAT?"
> "Ang chatbot po namin ay Gemini-powered at may kaalaman sa buong system — features, products, at maging sa orders ng naka-login na customer."

---

### 8. Closing (30 sec)
> "Sa kabuuan po: isang kumpletong e-commerce platform na may **limang AI features** — Virtual Try-On, AI Stylist, AI Design Generator, AI Product Generation, at AI Support — na may human oversight sa bawat AI decision, secure na server-side AI integration, at kumpletong admin operations — vouchers, sales reports na nae-export sa Excel, hanggang audit logging. Salamat po!"

---

## 🛡️ Q&A CHEAT SHEET

| Tanong ng panel | Sagot |
|---|---|
| **"Baka fake products lang 'yang AI?"** | Ituro ang Made to Order badge → print shop kami, parehong production ng custom orders → industry practice (Printify/Teespring) → may admin approval + papalitan ng totoong photo pag may sample |
| **"Bakit hindi React/framework?"** | Deliberate: multi-page server-rendered = mas simple, mas secure (server-side ang AI keys/validation), SEO-friendly, walang build step. React ay para sa complex SPAs — hindi 'yon ang problema namin. Future work: React Native mobile app |
| **"Magkano ang AI cost?"** | ~₱2-2.50 kada image generation (try-on/design/product), sentimo lang ang text (stylist/chatbot). Pay-per-use, walang monthly fee |
| **"Paano kung mali/pangit ang AI output?"** | Try-on: may regenerate + fallback messages. Design: pwedeng burahin/ulitin. Products: may admin review bago ma-save. Chatbot: may safety settings + steering |
| **"Secure ba?"** | API keys sa server-side .env (hindi umaabot sa browser), CSRF tokens sa lahat ng writes, prepared statements (SQL injection), MIME sniffing + EXIF stripping sa uploads, login-gated AI endpoints, audit log |
| **"Paano ang privacy ng camera/photos?"** | Camera off by default; frames ay pinoproseso lang para sa try-on; payment proofs may consent checkbox + RA 10173 privacy notice + retention policy |
| **"Ano ang kinaiba niyo sa Shopee/Lazada?"** | Hindi kami marketplace — specialized print shop na may AI-powered fitting at design tools na wala sa kanila |
| **"Bakit walang VAT ang custom orders?"** | Quote-based po ang custom pricing at VAT-inclusive na — ibang pricing model sa retail products |
| **"Made to Order pero may stock?"** | Ang stock po ng concept products ay production capacity — kung ilan ang kaya naming i-produce sa isang batch |
| **"Paano kung dalawang customer ang sabay gumamit ng huling voucher?"** | Atomic po ang pag-claim: iisang UPDATE na may kondisyong `times_used < max_uses`. Isa lang ang makakakuha; ang isa ay hindi matutuloy ang order at sasabihan kung bakit — hindi siya masisingil ng presyong hindi niya nakita |
| **"Pwede bang dayain ang voucher sa browser?"** | Hindi po. Ang browser ay pang-display lang; sa pag-place ng order, kinukuwenta ulit ng server ang presyo mula sa database at chine-check kung nasa wallet talaga ng customer ang voucher at hindi pa nagagamit |
| **"Paano kung ma-cancel ang order?"** | Babalik po ang voucher sa wallet ng customer at mababawas ulit ang bilang ng gamit |
| **"Bakit hindi mapalitan ang code kapag na-edit?"** | Naka-save po ang code sa bawat order at doon nakabatay ang "Given" totals — kapag pinalitan, masisira ang tracking. Gumawa na lang po ng bagong coupon |
| **"Pwede bang pagsabayin ang PWD at voucher?"** | Opo. Parehong kinukuwenta sa item subtotal, tapos ang 12% VAT ay sa natitira. Shipping voucher lang ang bumabawas sa delivery fee |
| **"Paano malalaman ng may-ari ang benta?"** | Sales Report: kahit anong date range, summary, daily chart, top products, by payment method, at Export Excel / Print. Pareho ang patakaran sa Dashboard: hindi kasama ang cancelled orders |
| **"Bakit hindi kasama ang cancelled sa benta?"** | Walang perang pumasok sa cancelled — pero nakalista pa rin ito (naka-strikethrough) at may hiwalay na bilang para transparent |

## 🚨 CONTINGENCY PLANS

| Problema | Gagawin |
|---|---|
| Mabagal/down ang internet o Gemini | Ipakita ang backup video recording; ituloy ang non-AI parts live |
| 429 rate limit sa demo | "One moment po" → Regenerate after ~30 sec; kung tuloy-tuloy, video backup |
| Ayaw ng camera sa venue | Upload Photo mode (may nakahandang full-body photo sa desktop) |
| Nag-crash ang XAMPP | Restart Apache/MySQL sa XAMPP control panel (~30 sec) |
| Projector washed out ang dark mode | Manatili sa light mode buong demo |
