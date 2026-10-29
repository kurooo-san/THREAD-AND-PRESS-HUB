# 🎓 Thread & Press Hub — Capstone Defense Demo Script

**Total demo time:** ~18–21 minutes (adjust per panel rules)
**Kailangan:** 2 browser tabs — Tab 1: customer (naka-login), Tab 2: admin
**Mas maganda kung may tablet:** Tablet = customer (hawak mo), Laptop = admin (naka-project). Makikita ng panel na pumapasok ang order sa admin nang walang reload.

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
- [ ] May **hiwalay na test order** (COD, walang voucher) na pang-cancel at pang-delete sa Step 6 — **huwag ang DEMO20 order**, kasi babalik ang voucher pag na-cancel at magiging "Used 0 / 5" sa Coupons
- [ ] May **province** sa profile ng demo customer (para "Delivers to … by …" ang lumabas sa product page, hindi ang pangkalahatang range)
- [ ] Isara ang mga hindi kailangang tabs/apps (para walang notification na sisingit)
- [ ] May **custom orders na "Delivered"** ngayong buwan, pinalitan ang status sa Admin → Custom Orders (para may numero ang "Avg lead time, custom" sa Sales Report; "—" ang lalabas kung wala)
- [ ] May laman ang **Slow-Moving Stock** at **Low Stock** sa Dashboard (kung parehong walang laman, ayos lang — may sariling paliwanag ang empty state)
- [ ] May ilang **product reviews** (para may maipakita sa Admin → Reviews sa Step 6)
- [ ] **Notification bell → Mark seen** sa admin, sa mismong device na gagamitin (naka-save ito per browser — kung hindi, 100+ ang lalabas na "New Orders")
- [ ] Kung tablet + laptop: parehong naka-open sa **live Railway URL** (hindi localhost — hindi maaabot ng tablet ang XAMPP ng laptop), at nasubukan na sa WiFi/hotspot ng venue
- [ ] Huwag i-minimize o ilipat sa ibang tab ang admin page habang hinihintay ang order — humihinto ang live update kapag nakatago ang page (babalik agad pagbalik)

---

## 🎯 SOP MAP — saan sinasagot ang bawat Specific Problem

Gamitin ito kapag tinanong ng panel ang isang item: puntahan ang step at sabihin ang sagot.

| SOP | Item | Saan ipapakita | Isang linyang sagot |
|---|---|---|---|
| 1.1.1 | Accuracy of order records | Step 6.2 Orders + Audit Log | Lahat ng order, discount at status change ay naka-record at naka-log kung sinong admin ang gumawa |
| 1.1.2 | Processing time | Step 5D + 6.0 + 6.2 | PayMongo ay awtomatikong nagkukumpirma ng bayad; lumalabas agad ang bagong order sa admin nang walang reload; isang click lang ang pagpalit ng status |
| 1.1.3 | Reduction of order errors | Step 5D + 5E + 5C | Naka-lock ang Place Order pagka-click (walang doble sa double-click), duplicate-order check, server-side na pag-compute ng presyo at voucher, stock check |
| 1.2.1 | Product stock availability | Step 2 (product page) + 6.1 Low Stock | "In stock / Only N left / Out of stock" sa customer; Low Stock panel + notification sa admin |
| 1.2.2 | Stock accuracy | Step 6.4 Cancel | Nababawas ang stock pag umorder, bumabalik pag na-cancel, isang beses lang |
| 1.2.3 | Reduction of overstock and unsold inventory | Step 6.1 Slow-Moving Stock | Nakikita ng admin ang mga produktong maraming stock pero walang benta sa 90 araw, kaya hindi na dadagdagan |
| 1.3.1 | Production efficiency | Step 4 + 6.3b Custom Orders | Malinaw na production stages: processing → printing → ready for pickup → delivered |
| 1.3.2 | Lead time | Step 6.5 Sales Report | Avg lead time (shop at custom), sinusukat mula sa oras ng order hanggang completed/delivered |
| 1.3.3 | Workflow transparency | Step 6.3b + Audit Log | Bawat status change ay naka-log na may oras; nakikita ng customer ang tracking page |
| 1.4.1 | Customer satisfaction | Step 6.3c Reviews | Product reviews at ratings; nakikita ng admin ang average rating bawat produkto |
| 1.4.2 | Communication efficiency | Step 7 Chatbot | 24/7 AI chatbot + handoff sa live support + email notifications |
| 1.4.3 | Real-time order status updates | Step 5D + 6.0 + 6.2 | Customer: tracking page na kusang nag-a-update + email sa bawat palit ng status. Admin: kusang pumapasok ang bagong orders, payments, designs at messages sa loob ng ~8 segundo, walang reload |
| 1.5.1 | Adoption of digital technology | Step 3, 4, 6.7 | Online ordering at payment, at limang AI features (kasama ang AI Generate Product) |
| 1.5.2 | Reduction of paper-based transactions | Step 5D + 6.5 | Digital invoice, online payment at proof upload, Excel export — walang papel na order form o resibo |
| 2.x | ISO 25010 | Q&A cheat sheet ("Secure ba?") + buong demo | Functional (buong flow), usability (responsive sa phone/tablet, dark mode), performance, compatibility (kahit anong browser/phone), reliability (live sa Railway), security (MFA, lockout, CSRF, audit log) |

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

- **Buksan ang isang product** → ituro ang 🚚 linya sa ilalim ng Add to Cart: *"Delivers to Metro Manila by Oct 22 – Oct 23"*
  > "May estimated delivery date po agad — naka-base sa area ng customer at sa shipping policy namin, at hindi binibilang ang Linggo. Kung guest, ang buong range ang ipinapakita."

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
5. *Optional:* sa **My Designs**, ituro ang **Remove design** button
   > "Pwede pong burahin ng customer ang sariling design para hindi kalat ang account niya. Pero kapag may order na gumagamit ng design, hindi po ito mabubura — para hindi madamay ang order at ang bayad."
6. *Optional, kung may tablet:* buksan ang Design sa tablet → ituro na maayos ang layout: 3D preview sa kaliwa, presyo at order details sa kanan, Submit sa ilalim
   > "Responsive po ang buong site — pareho ang features sa phone, tablet at desktop."
7. **Submit & Proceed to Order** → ipakita ang order summary → **Proceed to Payment** → ipakita na **parehong payment options** ang custom at regular orders: **Pay Online (PayMongo)** o **Cash on Delivery**

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
- Ituro ang **Estimated Delivery** sa summary → palitan ang Delivery Area (hal. Mindanao) → nagbabago ang petsa → piliin ang **Store Pickup** → nagiging **"Ready for Pickup"**
  > "Iisang table po ang pinagkukunan ng delivery fee at ng estimated date, kaya hindi sila magkakasalungat."
- *Optional:* kung may minimum spend ang voucher, ituro ang **"Add ₱… more to unlock"** progress bar
  > "Bawat voucher po ay chine-check ulit sa server — expiry, limit, minimum order, at kung sa customer talaga ito. Kahit baguhin pa sa browser, hindi po ito tatanggapin."

**D. Place order**
- Piliin ang **Cash on Delivery** → **Place Order** (pinakamabilis at walang aasahang internet)
- Ituro na naging **"Placing order…"** ang button at hindi na mapipindot ulit
  > "Naka-lock po ang button pagka-click, kaya kahit ma-double click, isang order lang ang mabubuo."
  > "May **Pay Online** din po through PayMongo — GCash, Maya, GrabPay o card. Sa secure page po ng PayMongo nagbabayad, kaya wala kaming hinahawakang card details, at awtomatikong nakukumpirma ang bayad."
- Sa Order Confirmation, ituro ang **Voucher (DEMO20)** line → *"Naka-record din po ito sa invoice at sa confirmation email."*
- Ituro ang 🚚 **Estimated delivery** sa ilalim ng Order Number (nandoon din sa My Orders → order details)

**E. Duplicate order check** *(optional, ~30 sec)*
- Idagdag ulit sa cart ang **parehong item, kulay, size at dami** → Checkout → Place Order
- Lalabas ang babala: *"You already placed an order with these same items 1 minute ago (Order #…)"* + link papunta sa order + checkbox
  > "Dalawang layer po ang proteksyon laban sa dobleng order. Una, naka-lock ang button pagka-click para sa double-click. Pangalawa, itong check na ito — para sa pangalawang tab, o kapag nag-Back at nag-order ulit. Hindi po namin hinaharangan, kasi minsan sinasadya ng customer na umorder ulit; kailangan lang niyang i-confirm. Sampung minuto pabalik po ang tinitingnan."
- Kapag tsinek ang box at itinuloy, may tala sa order para kay admin: **[Same items as Order #… - customer confirmed]** → ipapakita sa Step 6

---

### 6. Admin Side (6–7 min)
**Lipat sa Tab 2 (Admin)**

**Gawin (sunud-sunod):**
0. **⭐ Real-time — gawin ito habang nagpe-Place Order sa Step 5D** *(SOP 1.1.2, 1.4.3)*
   - Bago pindutin ang Place Order sa customer, nakabukas na ang **Admin → Dashboard** (o **Orders**) sa Tab 2 / sa laptop
   - Pagka-place ng order, **huwag i-refresh** — sa loob ng ~8 segundo kusang gagalaw ang **Total Orders, Revenue chart, Recent Orders**, ang **badge sa sidebar**, at tataas ang **notification bell** 🔔
     > "Hindi na po kailangang i-refresh ng admin ang page. Kada ilang segundo, tahimik na kumukuha ang page ng bagong data at ang nagbagong bahagi lang ang pinapalitan. Ganito rin po sa Payments, Custom Designs, Custom Orders, Users, Sales Report, Contact Messages at Audit Log."
   - *Kung may oras:* habang may tina-type ka sa admin (hal. notes sa Custom Designs), umorder ulit → hindi mabubura ang tina-type mo; lalabas ang bago pagka-save
     > "Hindi rin po nito ginagalaw ang form na ginagamit ng admin — naghihintay muna ito hanggang matapos siya."
1. **Dashboard** — ipakita ang KPIs + Revenue Trends chart (5 sec lang), tapos i-scroll pababa sa **Top Selling · Low Stock · Slow-Moving Stock** *(SOP 1.2.1, 1.2.3)*
   > "Sa iisang tingin po, alam ng may-ari kung ano ang mabenta, ano ang paubos na, at ano ang hindi gumagalaw — yung Slow-Moving po ay mga produktong marami pang stock pero walang benta sa 90 araw, kaya hindi na sila dadagdagan at hindi naiipon ang hindi nabebentang paninda."
2. **Orders** → buksan ang kaka-place lang na order → ituro ang **Voucher (DEMO20)** line → palitan ang status sa **Confirmed**
   > "Nakikita po ng admin ang bawat discount na ibinigay, at naka-log sa Audit Log ang mga pagbabago."
3. **Coupons** → ituro ang DEMO20: **Used 1 / 5** at ang **Given** na halaga
   > "Awtomatiko pong nabibilang ang gamit. Kapag naabot ang limit, hindi na po ito tatanggapin — kahit sabay pang mag-checkout ang dalawang customer sa huling slot."
   - *Optional:* pindutin ang **Edit** o **Deactivate** para ipakitang buo ang management
3b. **Custom Orders** → buksan ang custom order mula sa Step 4 → palitan ang status sa **Printing** *(SOP 1.3.1, 1.3.3)*
   > "Ito po ang production workflow: processing, printing, ready for pickup, delivered. Bawat palit ay naka-log na may oras — doon po namin kinukuha ang lead time na makikita natin sa Sales Report."
3c. **Reviews** → ituro ang average rating bawat produkto → i-**Hide** ang isang review, tapos **Unhide** *(SOP 1.4.1)*
   > "Dito po namin sinusukat ang customer satisfaction. Kapag spam o hindi angkop ang review, pwedeng itago — hindi na ito lalabas at hindi na bibilangin sa rating."
4. **Cancel → Delete** (gamitin ang **hiwalay na test order**, hindi ang DEMO20 order)
   - Kung ginawa ang Step 5E: buksan ang duplicate order → ituro sa **Notes** ang *[Same items as Order #… - customer confirmed]*
   - Palitan ang status sa **Cancelled**
     > "Pag na-cancel po, awtomatikong naibabalik ang stock at ang voucher. Kapag in-un-cancel, babawasan ulit ang stock — at kapag kulang na ang stock, hindi papayagan."
   - Lilitaw ang 🗑️ **Delete** button → pindutin → confirm
     > "Cancelled orders lang po ang pwedeng i-delete, at hindi kapag may bayad na — kailangang manatili ang record para sa refund. Kasamang nabubura ang payment screenshot, kaya dito po talaga natitipid ang storage. Naka-record pa rin sa Audit Log kung sinong admin ang nag-delete."
   - *Optional:* buksan ang **Audit Log** → *"Cancelled order #… permanently deleted"*
5. **Sales Report** (1 min)
   - Pindutin ang **This month** → ituro ang Total order value, Orders, Average order, at ang hati ng **Discounts given** (PWD/Senior vs Vouchers vs Shipping)
   - Ituro ang **Avg lead time, shop** at **Avg lead time, custom** *(SOP 1.3.2)*
     > "Ito po ang lead time — gaano katagal mula pag-order ng customer hanggang ma-complete o ma-deliver. Hindi po ito tantiya; kinukuwenta mula sa aktwal na oras na naka-log sa bawat status change."
   - I-scroll: **Daily sales chart → Top products → By payment method → Orders list**
     > "Ito po ang sagot kung paano malalaman ng may-ari ang benta sa kahit anong petsa — hindi na po kailangang mag-compute nang mano-mano."
   - Pindutin ang **Export Excel** → buksan ang file → ipakita ang 4 na sheet (Summary, Orders, Top Products, Daily Sales)
     > "Naka-format na po ito para sa accounting — may peso formatting, filters, at totals na hindi kasama ang cancelled orders."
   - *Kung kapos sa oras:* **Print** na lang ang ipakita (may sariling report header)
6. **AI Insights** (pindutin ang "AI Insights" button sa admin panel) → i-type: **"magkano discount natin this month?"**
   > "Pati po ang AI assistant ng admin ay alam ang vouchers at discounts — galing sa totoong data, hindi hula."
   - *Follow-up kung may oras:* **"ilang customer na ang nabigyan ng voucher at ilan pa ang hindi nagagamit?"**
7. **⭐ AI Generate Product — ang pang-finale** *(SOP 1.5.1)*:
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
**Ipakita ang handoff:** i-type ang "talk to a person" o "Gusto kong makausap ang tao" → diretsong lilipat sa Live Support kasama ang buong usapan (hindi na dadaan sa AI). I-type ang "back to AI" para bumalik sa AI.
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
| **"Paano kung ma-cancel ang order?"** | Babalik po ang voucher sa wallet ng customer at mababawas ulit ang bilang ng gamit. Kapag in-un-cancel ng admin, babawiin ulit ang voucher; kung nagamit na ito ng customer sa ibang order, hindi papayagan ang un-cancel para hindi magamit nang dalawang beses |
| **"Bakit hindi mapalitan ang code kapag na-edit?"** | Naka-save po ang code sa bawat order at doon nakabatay ang "Given" totals — kapag pinalitan, masisira ang tracking. Gumawa na lang po ng bagong coupon |
| **"Pwede bang pagsabayin ang PWD at voucher?"** | Opo. Parehong kinukuwenta sa item subtotal, tapos ang 12% VAT ay sa natitira. Shipping voucher lang ang bumabawas sa delivery fee |
| **"Paano malalaman ng may-ari ang benta?"** | Sales Report: kahit anong date range, summary, daily chart, top products, by payment method, at Export Excel / Print. Pareho ang patakaran sa Dashboard: hindi kasama ang cancelled orders |
| **"Bakit hindi kasama ang cancelled sa benta?"** | Walang perang pumasok sa cancelled — pero nakalista pa rin ito (naka-strikethrough) at may hiwalay na bilang para transparent |
| **"Real-time ba talaga? Paano?"** | Opo. Kada ~8 segundo, kumukuha ang admin page ng bagong data sa server at ang mga bahaging nagbago lang ang pinapalitan — walang buong reload, walang kurap. Ganoon din kadalas ang notification bell. Humihinto ito kapag nakatago ang tab para hindi nagsasayang ng request |
| **"Bakit hindi WebSocket / push?"** | Para sa laki ng tindahan namin, sapat at mas simple ang polling kada 8 segundo: gumagana sa kahit anong hosting (Railway, XAMPP) at walang hiwalay na server na kailangang patakbuhin. Kapag lumaki ang traffic, iyon ang susunod na hakbang |
| **"Paano kung may ine-edit si admin habang may pumasok na bagong data?"** | Hindi po ginagalaw ang bahaging may tina-type, may binagong dropdown, o may bukas na modal. Naghihintay ito hanggang ma-save o ma-clear, saka mag-a-update |
| **"Paano kung ma-double click ang Place Order?"** | Naka-lock po ang button pagka-click ("Placing order…"), kaya isang request lang ang napapadala. Pangalawang layer ang duplicate-order check para sa pangalawang tab o pag-order ulit |
| **"Responsive ba sa phone at tablet?"** | Opo — pareho ang features sa phone, tablet at desktop, kasama ang Design Studio at ang buong admin panel |
| **"Paano niyo nalalaman na duplicate ang order?"** | Parehong customer, parehong items (product, kulay, size, dami — kahit iba ang pagkakasunod), at may order na hindi cancelled sa loob ng huling 10 minuto. Babala lang, hindi block — kailangang i-confirm ng customer, at may tala sa order para makita ni admin |
| **"Paano kung mali talaga ang na-order na duplicate?"** | I-cancel ni admin → babalik ang stock at voucher → saka pwedeng i-delete |
| **"Saan galing ang estimated delivery date?"** | Sa shipping policy namin per area (Rizal/NCR 1–2 araw, karatig-probinsya 2–3, Luzon/Visayas/Mindanao 3–5), working days at hindi kasama ang Linggo. Iisang table ang pinagkukunan ng fee, ng petsa, at ng Shipping page. Sa lumang order, kinukuha ang area mula sa delivery fee na binayaran |
| **"Bakit kailangang i-cancel muna bago i-delete?"** | Para siguradong naibalik na ang stock at voucher bago mawala ang record. Hindi rin pwedeng i-delete ang may bayad na — kailangan iyon sa refund/dispute. Bawat delete ay naka-audit log |
| **"Malaki ba talaga ang natitipid sa pag-delete?"** | Maliit po ang order record mismo (~160 bytes). Ang malaki ay ang payment screenshot — kasama po iyong nabubura |
| **"Naibabalik ba ang stock pag na-cancel?"** | Opo, isang beses lang (may flag para hindi madoble). Pag in-un-cancel, babawasan ulit; kung kulang na ang stock, hindi papayagan |
| **"Pwede bang burahin ng customer ang design niya?"** | Opo, pero kung hindi pa ito ginagamit sa order. Kapag may order na, hindi na ito mabubura para hindi madamay ang order at ang bayad |
| **"Paano niyo sinusukat ang lead time?"** (1.3.2) | Mula sa oras ng pag-order hanggang sa unang pagkakataong naging Completed (shop) o Delivered (custom), base sa audit log. Average ito ng mga order sa napiling petsa sa Sales Report |
| **"Bakit '—' ang lead time ng custom?"** | Wala pang custom order na na-deliver sa napiling petsa. Kapag mayroon na, kusang lalabas ang numero |
| **"Ano ang Slow-Moving Stock?"** (1.2.3) | Aktibong produkto na may higit 5 stock pero walang benta (hindi kasama ang cancelled) sa huling 90 araw — parehong 90 araw ng Top Selling. Senyales ito na huwag nang dagdagan ang stock |
| **"Paano nababawasan ang papel?"** (1.5.2) | Digital ang order form, invoice (PDF), payment proof (upload), at sales report (Excel). Walang kailangang i-print para makapag-order o makapag-report |
| **"Paano ang customer satisfaction?"** (1.4.1) | Product reviews at star ratings mula sa customer; average rating bawat produkto sa Admin → Reviews; may hide para sa spam |

## 🚨 CONTINGENCY PLANS

| Problema | Gagawin |
|---|---|
| Mabagal/down ang internet o Gemini | Ipakita ang backup video recording; ituloy ang non-AI parts live |
| 429 rate limit sa demo | "One moment po" → Regenerate after ~30 sec; kung tuloy-tuloy, video backup |
| Ayaw ng camera sa venue | Upload Photo mode (may nakahandang full-body photo sa desktop) |
| Nag-crash ang XAMPP | Restart Apache/MySQL sa XAMPP control panel (~30 sec) |
| Projector washed out ang dark mode | Manatili sa light mode buong demo |
| Hindi pumapasok ang order sa admin nang live | Hintayin ang ~8 segundo; siguraduhing naka-focus ang admin tab (hindi minimized). Kung wala pa rin, i-refresh — naka-save na ang order. Malamang internet ang problema |
| Hindi maabot ng tablet ang site | Gamitin ang live Railway URL / i-scan ang QR; kung walang internet, gawin na lang sa 2 tab ng laptop |
