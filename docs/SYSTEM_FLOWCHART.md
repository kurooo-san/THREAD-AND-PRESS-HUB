# System Flowchart — User and Admin Side

**Thread & Press Hub** · System's Structure

Two traced paths through the live system: what a customer moves through from first visit to
leaving a review, and what an administrator moves through from sign-in to fulfilment.

Every decision below corresponds to a branch that exists in the source, not to an idealised
design. Where the build does not do something, it is not drawn.

- **Figures 1 and 2** are sized for the paper — few enough steps to stay readable when printed.
- **References A and B** carry every branch in the build, for questions the simplified figures invite.

> The Mermaid diagrams render automatically on GitHub. To export one as an image, paste its code
> block into <https://mermaid.live>. Pre-rendered PNGs of Figures 1 and 2 are also available.

---

## Symbols

Standard flowchart notation. The same four shapes carry the same meaning in every figure.

| Shape | Name | Meaning |
| --- | --- | --- |
| Rounded rectangle | Terminal | Where a path starts or ends |
| Rectangle | Process | An action the system or the person performs |
| Diamond | Decision | A branch, labelled on every outgoing arrow |
| Cylinder | Data store | A database table the step writes to |

---

## Figure 1 — User Side

From landing page to review. Both payment methods produce an order; only the online route waits
on an administrator first.

```mermaid
flowchart TD
    A(["Start"]) --> B["Browse products"]
    B --> C{"Registered?"}
    C -- "No" --> D["Register an account"]
    D --> E
    C -- "Yes" --> E["Sign in"]
    E --> F{"Credentials valid?"}
    F -- "No" --> E
    F -- "Yes" --> G["Select product, colour and size"]
    G --> H["Add to cart"]
    H --> I["Checkout — discount and coupon applied"]
    I --> J{"Payment method?"}
    J -- "Cash on Delivery" --> L
    J -- "GCash / Maya" --> K["Upload payment proof for admin verification"]
    K --> L[("Order recorded")]
    L --> M["Track order status"]
    M --> N["Receive order and leave a review"]
    N --> O(["End"])
```

---

## Figure 2 — Admin Side

One sign-in, one dashboard, five areas of work. Every task returns to the dashboard, so the shape
is a loop rather than a line.

```mermaid
flowchart TD
    A(["Start"]) --> B["Sign in"]
    B --> C{"Administrator account?"}
    C -- "No" --> D(["Return to storefront"])
    C -- "Yes" --> E["Admin dashboard — sales and order summary"]
    E --> F{"Select a task"}
    F -- "Products" --> G["Add, edit or restock catalogue items"]
    F -- "Orders" --> H["Update order status"]
    F -- "Payments" --> I{"Payment proof valid?"}
    I -- "Yes" --> J["Approve — order proceeds"]
    I -- "No" --> K["Reject — customer re-uploads"]
    F -- "Custom orders" --> L["Approve design, advance to printing"]
    F -- "Users" --> M["Edit, ban or remove"]
    G --> N[("Database updated")]
    H --> N
    J --> N
    K --> N
    L --> N
    M --> N
    N --> O{"More tasks?"}
    O -- "Yes" --> F
    O -- "No" --> P(["Sign out"])
```

---

## Reference A — Full user path

Not for the paper. Keep this on hand during the defence: if a panellist asks where custom apparel,
virtual try-on or the support channels sit, they are here.

The rate-limit and banned-account checks sit *inside* sign-in rather than before it, which is why a
wrong password and a locked account leave by the same arrow.

```mermaid
flowchart TD
    S(["Customer opens the site"]) --> BROWSE["Browse catalogue<br/>filter by gender, category,<br/>colour, size, price"]
    BROWSE --> PD["Open product page<br/>photo, price, stock,<br/>average star rating"]
    PD --> AUTH{"Signed in?"}

    AUTH -- "No" --> REG{"Has an account?"}
    REG -- "No" --> MKACC["Register<br/>name, email, phone,<br/>Senior / PWD ID optional"]
    MKACC --> LOGIN
    REG -- "Yes" --> LOGIN["Sign in"]
    LOGIN --> RATE{"Under 5 failed<br/>attempts?"}
    RATE -- "No" --> LOCK["Temporarily rate-limited"]
    LOCK --> LOGIN
    RATE -- "Yes" --> CRED{"Password correct<br/>and not banned?"}
    CRED -- "No" --> LOGIN
    CRED -- "Yes" --> ROLE{"Account type?"}
    ROLE -- "Admin" --> TOADMIN(["Go to Reference B"])
    ROLE -- "Customer" --> HUB

    AUTH -- "Yes" --> HUB{"What does the<br/>customer want?"}

    HUB -- "Buy ready-made" --> PICK["Choose colour, size,<br/>quantity"]
    PICK --> CART["Add to cart"]
    CART --> CHK["Checkout<br/>delivery or pickup,<br/>Senior / PWD 20% discount,<br/>coupon code"]
    CHK --> PAY{"Payment method?"}

    PAY -- "Cash on Delivery" --> ORD1[("orders<br/>status = pending")]
    ORD1 --> CONF["Order confirmation"]

    PAY -- "GCash / Maya" --> QR["Scan merchant QR,<br/>upload receipt screenshot<br/>+ reference number"]
    QR --> PROOF[("payment_submissions<br/>payment_status =<br/>pending_verification")]
    PROOF --> WAIT["Awaiting admin verification"]
    WAIT --> CONF

    CONF --> TRACK["Track order<br/>pending → confirmed → preparing<br/>→ out for delivery → completed"]
    TRACK --> GOT{"Order received?"}
    GOT -- "Not yet" --> TRACK
    GOT -- "Yes" --> RVW["Leave a rating and review<br/>verified purchase only,<br/>one per product"]
    RVW --> RVDB[("product_reviews")]
    RVDB --> E(["End"])

    HUB -- "Design custom apparel" --> DS["Design Studio<br/>pick garment, colour, size;<br/>add text and artwork"]
    DS --> AIS{"Use AI design<br/>assistant?"}
    AIS -- "Yes" --> GEN["Describe the idea →<br/>Gemini returns print artwork"]
    GEN --> SAVE
    AIS -- "No" --> SAVE["Save design"]
    SAVE --> CDDB[("custom_designs<br/>status = pending")]
    CDDB --> SUM["Order summary<br/>base + print + colour cost"]
    SUM --> CPAY{"Payment method?"}
    CPAY -- "Cash on Delivery" --> CO[("custom_orders<br/>status = payment_uploaded")]
    CPAY -- "GCash / Maya" --> CPROOF["Upload receipt<br/>+ reference number"]
    CPROOF --> CO
    CO --> CTRACK["Track custom order<br/>payment verified → processing →<br/>printing → ready for pickup → delivered"]
    CTRACK --> E

    HUB -- "Try it on" --> TRY["Virtual Try-On<br/>upload a photo →<br/>Gemini composites the garment"]
    TRY --> E
    HUB -- "Get help" --> SUP["Support chat, chatbot,<br/>or contact form"]
    SUP --> E
```

---

## Reference B — Full admin path

Payment verification is the only place a customer-facing order state changes without the customer
acting, and rejection loops the customer back to re-upload rather than cancelling the order. The AI
product generator writes nothing until the draft is approved.

```mermaid
flowchart TD
    AS(["Administrator signs in"]) --> ACHK{"Account type<br/>is admin?"}
    ACHK -- "No" --> BACK(["Return to storefront"])
    ACHK -- "Yes" --> DASH["Admin dashboard<br/>revenue, order counts,<br/>order-status breakdown,<br/>low stock, pending reviews"]
    DASH --> TASK{"Which area?"}

    TASK -- "Products" --> PMODE{"Add a product how?"}
    PMODE -- "Manually" --> MAN["Fill the form,<br/>upload a photo"]
    PMODE -- "AI generate" --> PROMPT["Describe the idea"]
    PROMPT --> GEM["Gemini returns listing copy,<br/>print artwork, catalogue photo"]
    GEM --> REVIEW{"Approve the draft?"}
    REVIEW -- "No" --> PROMPT
    REVIEW -- "Yes" --> MAN
    MAN --> PDB[("products")]
    PDB --> PMAINT["Edit, set stock,<br/>download print file,<br/>deactivate"]
    PMAINT --> DASH

    TASK -- "Payments" --> VIEWP["Open the submitted<br/>receipt and reference number"]
    VIEWP --> VALID{"Payment<br/>legitimate?"}
    VALID -- "Yes" --> APPR[("payment_status = verified<br/>order released to fulfilment")]
    VALID -- "No" --> REJ[("payment_status = rejected<br/>customer asked to re-upload")]
    APPR --> DASH
    REJ --> DASH

    TASK -- "Orders" --> OSTEP["Advance the order<br/>pending → confirmed → preparing<br/>→ out for delivery → completed"]
    OSTEP --> ODB[("orders")]
    ODB --> DASH

    TASK -- "Custom designs" --> CDEC{"Design printable<br/>as submitted?"}
    CDEC -- "Yes" --> CAPP["Mark approved"]
    CDEC -- "No" --> CREV["Request a revision<br/>with admin notes"]
    CAPP --> CPIPE
    CREV --> CPIPE["Advance the custom order<br/>payment verified → processing →<br/>printing → ready for pickup → delivered"]
    CPIPE --> CODB[("custom_orders")]
    CODB --> DASH

    TASK -- "Users" --> UACT{"Action?"}
    UACT -- "Edit details" --> UDB[("users")]
    UACT -- "Ban or unban" --> UDB
    UACT -- "Delete" --> UDB
    UDB --> DASH

    TASK -- "Messages" --> MSG["Answer contact messages<br/>and support chat"]
    MSG --> DASH

    TASK -- "Audit log" --> LOG[("audit_log<br/>read-only trail of<br/>every admin action")]
    LOG --> DASH

    DASH --> OUT{"Finished?"}
    OUT -- "No" --> TASK
    OUT -- "Yes" --> AE(["Sign out"])
```

---

## Where each decision lives

If the panel asks whether the flowchart matches the build, this is the answer: every diamond maps
to a specific branch in a specific file.

| Decision | File | What it checks |
| --- | --- | --- |
| Credentials valid? | `login.php` | `password_verify()`, the `users.status` column, and a 5-attempt limit from `login_attempts` |
| Administrator account? | `login.php` | `user_type === 'admin'` routes to the admin dashboard |
| Payment method? | `checkout.php` | Cash on Delivery confirms immediately; other methods redirect to the QR page |
| Payment proof valid? | `admin/payment-verification.php` | Approve sets `verified`; reject sets `rejected` |
| Order received? | `orders.php` | The review form unlocks once the order reaches the customer |
| Verified purchase? | `includes/reviews.php` | Requires a non-cancelled order containing that product |
| Approve the draft? | `admin/ai-product-ajax.php` | Generation is a draft; nothing is inserted until Save |
| Design printable? | `admin/custom-designs.php` | Sets `approved` or `revision` with admin notes |

---

## Status pipelines

The two fulfilment pipelines referenced above, taken from the column definitions.

**`orders.status`**

```
pending → confirmed → preparing → out_for_delivery → completed
                                                   ↘ cancelled
```

**`orders.payment_status`**

```
unpaid → pending_verification → verified
                              ↘ rejected → (customer re-uploads)
```

**`custom_orders.status`**

```
pending_payment → payment_uploaded → payment_verified → processing
    → printing → ready_pickup → delivered
                              ↘ cancelled
```

---

## Scope note

The `mfa_otps` and `gcash_transactions` tables exist in the schema, but no code path currently
reaches them, so they are not drawn.

Showing a step the build does not perform is the one thing that turns a flowchart into a liability
during a defence.
