---
id: DSGN-001
type: design
status: resolved
created: 2026-08-27
resolved: 2026-08-27
---

# DSGN-001: Seller-problem-driven configurator UI

## Problem
The seller configurator UI (prototype/php/src/resources/views/seller/listings/*, Http/Controllers/Seller/*; built by FEAT-026) is organized by data model: one screen per table — axes/options, variant grid, units, modifiers + scopes, quantity breaks, description sections — hub-linked from the listing edit page. A seller must already speak the model's vocabulary ("axis", "variant", "serialized unit", "modifier scope") to find the screen that answers their actual problem ("charge more for 2XL", "stop asking blank-mug buyers for a name"). prototype/php/__local__/seller-user-stories.md captures 43 stories in sellers' own words; the current UI answers them only through model-first translation the seller performs themselves.

## Goal
A seller who knows their craft and nothing about the platform's data model can set up every story scenario without translation.

## Outcome
1. A design — produced with the /design skill as a design canvas — showing the seller-problem-driven configurator flow: entry points named by seller intent, progressive disclosure (a simple listing never sees configurator machinery), craft-language copy, and the buyer-visible consequence of each seller choice shown where the choice is made.
2. The shipped UI that design specifies, replacing the model-driven screens.
3. Validation is story-by-story against the embedded user-story list: every story Appendix A marks v1 or partial is accomplishable through the new UI by a seller following only the story's own words, each verified by a feature test or a recorded manual walk; stories marked deferred or gap have a designed place in the flow (visible, honest, not shipped).
4. The ticket embeds the full user-story list verbatim as its validation set.

## Why it matters
The configurator's data model was built from these sellers' observed workarounds, but the UI over it re-imposes the translation burden the model was meant to remove — the recurrence across FEAT-026/BUG-005/FEAT-032/IMPRV-011 shows the surface being re-patched screen by screen instead of designed once from the seller's side.

## Discovery notes
Advisory design-layer principles, not directives: organize by seller intent rather than by table (offering choices / pricing them / buyer input / stock / describing); progressive disclosure — the 8x10-print seller never meets the machinery; show the buyer's view beside the seller's controls (the B10/D2 class of problems); publish validation as the plain-language checklist E2 describes. The data model, actions, and domain layer are sound and unchanged — this is a UI/UX/CX redesign over the existing Actions/Domain; the maker decides technical shape. The /design skill produces the canvas for human review before implementation. Physical-goods-only platform scope stands; Appendix A's coverage column is the authority on which stories bind the shipped UI versus bind only the design. The JS-off contract applies to the shipped UI as everywhere.

## Related work
- FEAT-026 (the model-driven UI being replaced)
- BUG-005 (its sharpest observed UX failure)
- FEAT-029, FEAT-031, FEAT-032 (taxonomy/attribute reworks of the same surface)
- IMPRV-010, IMPRV-011 (buyer-side polish of the same feature)
- prototype/php/docs/item-configurator.md
- __local__/item-configuration/etsy-product-configuration.md §2.2

## User stories (validation set)

Copied verbatim from prototype/php/__local__/seller-user-stories.md (2026-08-27). Validation of this ticket is against these stories; Appendix A's coverage column decides which bind the shipped UI (v1, partial) and which bind only the design (deferred, gap).

## A. Offering choices, and pricing them

### A1. Multiple sizes, colors, and materials at different prices
**Seller**: Candle maker selling wax melts in multiple scents and sizes
**Story**: "I need to sell the same candle in three sizes and eight scents, where the bigger sizes cost more to make and should cost more to buy. Right now I can only set one price for the whole listing, so I either overprice the small candles or undersell the large ones. I want to set a different price for each size while keeping scent as a separate choice that doesn't affect price."

### A2. A size upcharge that applies in every color
**Seller**: T-shirt printer selling graphic tees
**Story**: "I need the price to bump up for 2XL and larger, but only because the blank shirts cost me more in those sizes — it has nothing to do with which print color the customer picks. Right now the only way I can charge more for the bigger sizes is to write out a separate color-and-size combination for every single one, even though the upcharge is the same across every color. I want to set one upcharge for the larger sizes and have it apply no matter which color someone chooses."

### A3. Adding a new size that only exists in one material
**Seller**: Jeweler selling stacking rings in silver, gold, and rose gold
**Story**: "I need to add a size 11 to my ring listing, but I only have the mold to cast it in gold — not silver or rose gold yet. Right now if I add size 11 as a choice, customers can pick it in any metal, including ones I can't actually make it in. I want to add the new size and only turn it on for the metal I can actually offer, without touching the sizes and metals I already have set up."

### A4. A ring with more choices than two dropdowns can hold
**Seller**: Jeweler selling engraved name rings
**Story**: "I need customers to choose finish, which side gets engraved, ring size, and band width — four separate decisions, and engraving both sides costs an extra $8.50. Right now I only have two option slots, so I've had to jam finish and engraving side into one dropdown option like 'Gold - Both Sides' and size and width into another like '7 - 4mm', which is confusing for customers and a nightmare for me to keep priced correctly. I want each of those four choices to be its own clear dropdown, with the price adjusting correctly no matter which ones a customer combines."

### A5. Combinations that don't actually exist
**Seller**: Potter selling glazed mugs
**Story**: "I need to leave out combinations I don't actually make — I don't glaze the extra-large mug in the crackle finish because the glaze cracks too much at that size. Right now every color shows as available in every size, so customers pick the extra-large crackle mug, pay for it, and I have to message them to cancel and refund. I want to only offer the combinations I actually make, so customers can't select or pay for ones that don't exist."

### A6. One color selling out while others are still available
**Seller**: Mug maker selling a personalized mug in ten colors
**Story**: "I need customers to still be able to buy my mug in the colors I have on hand even after one color runs out. Right now when a color sells out, I have to notice it, edit the listing, and remove that color myself, or risk selling something I can't ship. I want the sold-out color to show as unavailable automatically while every other color stays purchasable, with no manual cleanup on my end."

### A7. Different stock counts for different combinations
**Seller**: Weaver selling wool throw blankets in several colors and sizes
**Story**: "I need to track how many I have left of each color-and-size combination separately, because I might have twelve of the small grey blanket but only two of the large mustard one. Right now my stock count is one number for the whole listing, so it doesn't reflect that some combinations are nearly gone while others are well stocked. I want to set and track inventory per combination so the listing accurately shows what's actually available in each one."

### A8. Updating prices across dozens of combinations without redoing them all
**Seller**: Furniture maker selling made-to-order dining tables by length and width
**Story**: "I need to price my table by length and width, and right now that means over 130 length-and-width combinations, each priced by hand. Right now if my lumber cost goes up, I have to open every single one of those 130-plus entries and retype the price, one at a time, which takes hours and I inevitably miss a few. I want to update prices across many combinations at once — for example, add a flat amount to every size over a certain length — instead of retyping each one individually."

### A9. A choice that needs its own price shown before checkout
**Seller**: Framer selling custom picture frames in several sizes and frame styles
**Story**: "I need customers to see how much a frame style costs before they commit to it, since an ornate wood frame costs a lot more than a simple black one. Right now the listing only shows one starting price, and the customer doesn't find out that their chosen frame style tacked on $40 until they're already at checkout. I want every option to show its own price impact right where the customer picks it, so there are no surprises at checkout."

### A10. A price that shows up whole, not as a range, before checkout
**Seller**: Woodworker selling dimension-priced tables with several optional add-ons
**Story**: "I need my buyer to see one real number for their exact table before they add it to the cart — their chosen size, plus a custom stain, plus rush production — not a vague 'from $699.75' that only firms up after they've picked everything. Right now the listed price is just the cheapest possible configuration, and buyers message me confused about what they'll actually be charged once every choice is added up. I want every choice they make to visibly add or change the price as they go, so the total they see right before buying is the exact total they'll be charged."

### A11. Knowing which exact combination a customer ordered
**Seller**: Leatherworker selling custom wallets in multiple leathers and stitching colors
**Story**: "I need to know at a glance, from my order list, exactly which leather and thread color combination someone bought without opening each order and reading through the notes. Right now I have to click into every single order and parse a line of text to figure out what to pull and make. I want each combination to have its own clear identifier so I can scan my order list and immediately know what to cut and stitch for each one."

### A12. A commission with too many small decisions to cram into two lists
**Seller**: Pet portrait artist selling custom watercolor commissions
**Story**: "I need to offer a commission that varies by number of pets, pose, and whether it's a digital file, an unframed print, or a framed print in several colors — more choices than any simple two-dropdown setup can represent cleanly. Right now I've had to mash pet count and pose into one dropdown option and print size and frame color into another, so customers see cryptic combined labels instead of picking each thing separately. I want to offer each of these as its own clear choice, priced correctly no matter how a customer combines them."

---

## B. Personalization and buyer input

### B1. Engraving text with a length limit
**Seller**: Ring engraver running a personalized jewelry shop
**Story**: "I need to collect the name or phrase a buyer wants engraved inside their ring band. Right now buyers type in whatever they want, and I've had submissions too long to fit on a 4mm band, so I end up messaging back and forth to ask them to shorten it before I can even start the piece. I want to set a maximum number of characters on the text field so a buyer can't submit something that won't physically fit, and see the limit before they type."

### B2. Charging extra just to personalize an item
**Seller**: Ceramic mug maker
**Story**: "I need to charge a couple dollars more when a buyer wants their mug personalized with a name, because it takes extra time to hand-letter each one. Right now my only options are to build the personalization cost into my base price for everyone — even buyers who just want a plain mug — or set my price low and lose money on every personalized order. I want to add an extra charge specifically for the personalization option so plain-mug buyers pay the plain price and personalized buyers cover the extra work."

### B3. Charging more for a nicer paper stock
**Seller**: Wedding invitation printer
**Story**: "I need to let a buyer choose which paper their invitations get printed on — my standard cardstock, a linen finish, or a premium cotton stock — and charge more for the nicer options. Right now I've had to bury paper stock in my personalization box as plain text, which means I can't attach a price difference to it, so I either eat the cost of the upgrade or chase the buyer down after the order to invoice them separately. I want the paper choice to be a proper list I control, with its own price for each option, so the total updates automatically when they pick something nicer."

### B4. Letting the buyer pick a thread color I control
**Seller**: Embroidered sweater and apparel maker
**Story**: "I need to offer a set list of thread colors for monogramming — the colors I actually stock — rather than letting buyers request anything they imagine. Right now I get personalization notes asking for colors I don't carry, like a bright orange I've never used, and I have to message the buyer to explain what's actually available and get them to pick again, which delays the order. I want to give buyers a list of the exact colors I offer to choose from, so what they select is always something I can actually stitch."

### B5. Making the monogram optional, not mandatory
**Seller**: Custom cutting board and sign maker
**Story**: "I need some buyers to be able to skip the monogram entirely and just get a plain board, while other buyers who want their initials on it are forced to fill that field in before they can check out. Right now every personalization field on my listing works the same way, so I either force everyone to type something — which means blank-board buyers make something up just to get through checkout — or I make it optional for everyone and end up with orders that are missing the monogram I actually needed to cut. I want to decide, field by field, whether an answer is required before the buyer can buy."

### B6. Not asking for a name when the buyer picked the blank mug
**Seller**: Ceramic mug maker
**Story**: "I need my personalization text box to disappear when a buyer selects the blank, undecorated version of my mug, and show up when they pick a decorated one. Right now the text box shows no matter what they pick, so blank-mug buyers see a field asking for a name and either leave it blank in confusion or fill it in anyway, and I have to guess whether they actually wanted it engraved. I've resorted to adding a note in my description telling people to ignore the box if they bought the blank mug, but plenty still miss it. I want the question to only appear for the options it actually applies to."

### B7. Pricing a custom length by the inch
**Seller**: Leather belt and strap maker
**Story**: "I need buyers to tell me their exact waist measurement in inches and have the price adjust based on how much material a longer belt uses. Right now I list a handful of preset sizes as separate options and either round buyers up to the nearest size or lose money making an oversized belt at the same price as a small one. I want a field where the buyer enters their measurement in inches and the price scales with the number they type, so I'm not guessing or absorbing the cost of extra leather."

### B8. Getting the buyer's photo before I start the piece
**Seller**: Pet portrait watercolor artist
**Story**: "I need the buyer's photo of their pet before I can start painting, since the whole commission is built from that reference image. Right now the purchase happens with no way to attach a file, so I have to message every buyer after they order and wait for them to reply with a photo, which can take days and sometimes never happens, leaving me with a paid order I can't start. I want buyers to upload their reference photo as part of placing the order so I have what I need the moment the sale comes in."

### B9. Making sure the buyer's answers actually reach me with the order
**Seller**: Custom name necklace maker
**Story**: "I need the spelling and font the buyer chose for their necklace to travel with the order into my shop's order details, not just sit in a message thread I might miss. Right now I've had buyers type their personalization into a note that ends up disconnected from the order itself, and I've started production on a piece before realizing I never actually saw their spelling, or found it days later buried in unread messages. I want whatever the buyer enters to be attached directly to the order I see when I go to fulfill it, every time, with nothing left to a separate conversation."

### B10. Letting the buyer actually see the font before they choose it
**Seller**: Wedding calligrapher and stationer
**Story**: "I need buyers to be able to compare what each font choice actually looks like before they pick one for their invitations. Right now the font names are just words in a dropdown list, so I have to post sample images somewhere in my photo gallery and hope the buyer scrolls back to find the one labeled 'Font 3' and matches it to the right name in the list. I get orders every week where the buyer picked a font by name alone and it's not what they thought it looked like, and I end up redoing the proof. I want each choice in the list to show its own preview so there's no guessing."

---

## C. One-of-a-kind, bulk, and made-to-order stock

### C1. One listing for fifty-two candlesticks, not fifty-two listings
**Seller**: Vintage dealer selling a lot of similar but non-identical brass candlesticks
**Story**: "I need to sell 52 candlesticks that are each a little different — different height, different dents, different price. Right now my only option is one listing per candlestick, which means 52 sets of photos, 52 descriptions, and none of them share the reviews or search ranking my shop has built up. I want one listing where a buyer can see all 52, each with its own photo, condition note, measurements, and price, and choose the exact piece they're taking home."

### C2. A sold one-of-a-kind piece has to vanish the second it sells
**Seller**: Vintage dealer with a numbered lot of unique pieces in one listing
**Story**: "I need the piece a customer just bought to stop being offered to anyone else the moment the sale goes through. Right now I'm nervous about someone else buying the same physical candlestick before I catch it and take it down manually. I want that one piece pulled from what's available immediately, while the other 51 stay right where they are, untouched."

### C3. Cheaper per card the more cards you order
**Seller**: Stationer selling custom-printed wedding invitations
**Story**: "I need to price my cards so that ordering 200 costs less per card than ordering 50, the way my print costs actually work. Right now I fake this by making 'Quantity: 50,' 'Quantity: 100,' 'Quantity: 200' each their own option and hand-typing a different total price into each one. I want to set a few quantity breakpoints with their discount, and have the price per card drop automatically as the order gets bigger, without me maintaining seven separate totals."

### C4. Selling by weight, not by the piece
**Seller**: Bead and craft-supply lot seller
**Story**: "I need to sell a bag of mixed glass beads priced by weight — 100 grams for a set price — instead of pricing each bead individually. Right now I just write 'price is for 100 grams' in the description and hope buyers read it, because the platform only knows how to charge per item. I want the listing itself to charge correctly for a lot sold by weight, so the price on the page is the price the buyer actually owes."

### C5. Some finishes are ready to ship, some are made to order — in the same listing
**Seller**: Jewelry maker offering the same ring in silver and gold
**Story**: "I need to tell buyers that the silver version of my ring ships tomorrow because I keep it in stock, while the gold version takes three weeks because I only cast it once it's ordered. Right now I can only set one shipping timeline for the whole listing, so either I'm overpromising on gold or underselling how fast silver actually ships. I want each finish to carry its own honest ready-to-ship or made-to-order timeline within one listing."

### C6. Pricing a table by its length and width without hand-typing 136 prices
**Seller**: Furniture maker selling custom dining tables
**Story**: "I need to price my tables the way I actually cost them — length times width, roughly proportional to the wood used. Right now I have to chop length into 17 fixed options and width into 8, then hand-calculate and type in a price for every one of the 136 combinations, and I have to redo the whole grid if my lumber cost changes. I want to set the pricing logic once — a rate per unit of size — and have every size in between price itself correctly."

### C7. A buyer wants a size I don't offer as a preset
**Seller**: Furniture maker with a fixed list of length and width options
**Story**: "I need a way to sell a table at 45 inches long when my listed sizes jump from 40 to 48. Right now that customer has to message me, I quote them by hand, and there's no way to actually charge and fulfill that order through the listing itself — the sale happens entirely outside the system. I want the buyer to be able to enter the exact size they want and get a real price back, without either of us falling back to a manual conversation just to buy something bigger than average."

### C8. One artwork, sold as a digital download and a physical print, same listing
**Seller**: Portrait artist selling watercolor pieces
**Story**: "I need to offer the same portrait as an instant digital file for people who just want the image, and as a printed, shipped piece for people who want something on their wall — at different prices, in the same listing. Right now offering both means either splitting into two listings and losing the shared reviews, or burying the digital option inside a physical listing where it still drags along shipping and processing time that doesn't apply to a file. I want one listing where a buyer picks digital or print and only sees the details — shipping time, file delivery — that actually apply to their choice."

### C9. The same mug, decorated or plain, without splitting the listing
**Seller**: Ceramics seller offering a personalized and a blank version of one mug
**Story**: "I need to sell my mug both personalized with custom text and completely blank, at different prices, without starting a second listing that loses the 280 reviews the first one has earned. Right now I've stuffed 'blank mug' in as a size option, so every blank-mug buyer still gets shown the box asking for their custom text, and I have to explain in the description to just leave it empty. I want the personalization question to disappear entirely for anyone who picks the blank version, so no one is confused or asked for something that doesn't apply to what they bought."

### C10. A custom quote shouldn't be something a stranger can click and buy
**Seller**: Stationer who negotiates bespoke pricing for repeat and custom clients
**Story**: "I need to give one specific customer a custom price for their unique order without that price showing up as a public option anyone can select. Right now my only way to offer a negotiated price inside the listing is to add it as a named dropdown choice — 'Custom Order for Preet, $461.25' — which any other buyer can see and buy for themselves at that price. I want to send a customer a private, one-time price that only they can accept, instead of publishing my negotiated deals to the whole internet."

### C11. Knowing what's actually in stock when everything is configured differently
**Seller**: Furniture maker who sells one-of-a-kind pieces and made-to-order variants under the same roof
**Story**: "I need my stock count to stay accurate whether I'm selling one unique reclaimed-wood table, a made-to-order table in a size a customer picked, or a bulk lot of coasters sold by the dozen. Right now I track pieces in a personal spreadsheet because the listing's single quantity number can't tell the difference between 'this exact table is gone forever' and 'I can make another one in two weeks' or 'I have 40 coasters left in this batch.' I want the platform to know, for each kind of item I sell, when something is truly sold out versus just waiting to be made, so I never accidentally sell the same one-of-a-kind piece twice or tell a buyer something is available when it isn't."

---

## D. Describing the item and being found

### D1. Organize my description without faking headers
**Seller**: Jewelry maker who hand-stamps custom rings
**Story**: "I need to organize my listing page into clear sections — how to order, materials, care instructions — without it reading as one giant paragraph. Right now I fake headers by typing emoji and ALL CAPS into the text box, and it still looks messy and inconsistent from listing to listing. I want the page to show clean, separated sections automatically, the way a real product page looks, without me hand-formatting anything."

### D2. Make sure buyers actually see my ordering steps
**Seller**: Personalized mug seller who requires custom text before shipping
**Story**: "I need buyers to see and follow the exact steps for submitting their personalization before checkout. Right now my 'HOW TO ORDER' instructions are just more text buried halfway down a long description, so I get orders every week with no name typed in, or spelling nobody double-checked, and I have to message the buyer and delay the order. I want those steps to be impossible to miss — shown right where the buyer is choosing options, not hoping they scroll far enough to read them."

### D3. Give buyers a real size chart, not a screenshot pasted into text
**Seller**: Apparel seller selling shirts across S–4XL
**Story**: "I need to show buyers a size chart with both body measurements and actual garment measurements so they order the right size. Right now I paste a chart as plain text or an image into the description, it displays as a wall of numbers with no formatting, and I still get 'this runs small' complaints and size-related returns. I want a chart that displays clearly as an actual table, with a note on whether the numbers are the buyer's body or the finished garment, so buyers can tell the difference before they buy."

### D4. Stop retyping the same disclaimers on every listing
**Seller**: Ceramics seller with 40 active listings
**Story**: "I need to warn buyers that colors may look different on their screen and that each handmade piece varies slightly, on every single listing I have. Right now I copy-paste that disclaimer into the bottom of each description by hand, and when I want to reword it I have to go edit it in 40 places one at a time. I want to write that disclaimer once and have it show consistently everywhere it belongs, so a wording fix takes one edit instead of forty."

### D5. Find the right category and describe my item without guessing at fields
**Seller**: New seller listing hand-poured candles for the first time
**Story**: "I need to put my item in the category buyers would actually search under and fill in the facts that matter for candles — wax type, scent, burn time — so it shows up when someone filters for those. Right now I don't know what 'category' even means for my item versus a similar-looking one, and I'm not sure which facts buyers can search by versus which are just decoration on the page. I want the listing screen to walk me to the right category and then only ask me for the facts that actually apply to what I'm selling."

### D6. Let me describe a material that isn't in the dropdown
**Seller**: Vintage textile seller working with reclaimed and blended fabrics
**Story**: "I need to say exactly what my item is made of, but my fabric is a reclaimed wool-linen blend and the material list only offers plain 'wool' or 'linen.' Right now I either pick the closest wrong answer or leave it blank and hope buyers read my description instead, and either way I lose people filtering by the material they actually want. I want to be able to add my own material when nothing on the list fits, without losing the ability to be found by the standard options too."

### D7. Spell out exactly what files a buyer gets and what they can do with them
**Seller**: Digital pattern designer selling PDF sewing patterns
**Story**: "I need buyers to know precisely which files they'll receive, what format they're in, and whether they're allowed to use my pattern to sell finished items or only for personal use, before they buy. Right now that's all buried in prose in the description alongside everything else, so I get messages asking 'wait, what do I actually download' and disputes from buyers who assumed commercial use was included. I want the file list and the license terms shown as their own clear, unmissable part of the listing."

### D8. Explain why there are no returns on a digital item, right where it matters
**Seller**: Digital printable wall art seller
**Story**: "I need buyers to understand, before they pay, that digital downloads can't be returned or refunded because there's no way to take the file back. Right now that notice is one more line lost in a long description, and I still get refund requests from buyers who say they didn't realize it was final. I want that no-returns notice to show clearly and consistently on every digital listing, not something I have to remember to write in every time."

---

## E. Managing the listing over time

### E1. Edit a listing without changing what a buyer already paid for
**Seller**: Seller raising prices on a popular pendant after a supply cost increase
**Story**: "I need to raise the price and update the options on my pendant listing without touching the orders people already placed at yesterday's price. Right now I'm nervous that editing a live listing might quietly change what's on an order still being made, so I sometimes leave outdated pricing up longer than I should just to avoid the risk. I want to make changes going forward and know, with certainty, that every order already placed still shows exactly what that buyer agreed to pay."

### E2. Tell me what's missing before I try to publish, in plain terms
**Seller**: Part-time seller listing a new product line on a weekend
**Story**: "I need to know exactly what's stopping my listing from going live when I hit publish. Right now I get a vague rejection that doesn't tell me which field is the problem, so I click through every screen guessing until I find it. I want a plain-language list of exactly what's missing — like 'add a material' or 'set a price for the large size' — with each one taking me straight to the screen where I fix it."

### E3. Sell my rush fee and gift wrap alongside the item, not as a separate hunt
**Seller**: Custom portrait artist who offers rush turnaround and gift wrapping
**Story**: "I need buyers to be able to add rush processing or gift wrap right when they're buying the portrait, in the same purchase. Right now those are their own separate listings, so buyers have to find them, remember to add them, and buy them separately — and most people either don't notice they exist or forget the second listing at checkout, so I lose that extra income. I want to offer them as add-ons right on the main listing so a buyer can just check a box and pay for everything at once."

---

## Appendix A. Coverage map

Each story against the research evidence it came from and the design doc's
current position. Coverage values: **v1** (mechanism in `docs/item-configurator.md`
§2–§6), **partial** (part of the outcome lands in v1), **deferred** (named in §9),
**gap** (no mechanism and not listed as deferred).

| Story | Evidence (research doc)                    | Coverage | Design-doc mechanism / note                                          |
| ----- | ------------------------------------------ | -------- | -------------------------------------------------------------------- |
| A1    | §2 mug, tee                                | v1       | Option surcharges; axis without surcharges stays price-neutral       |
| A2    | §2 POD tee size-tier upcharges             | v1       | Surcharge on the size axis's values                                  |
| A3    | §2.1 compound options                      | v1       | Sparse variants — create only the gold size-11 row                   |
| A4    | §2.2 engraved-ring case                    | v1       | Uncapped axes, variant-count cap                                     |
| A5    | §2.1 disabled combinations                 | v1       | Sparse variants / `enabled`                                          |
| A6    | §2 per-option stock behavior               | v1       | Availability resolution greys unavailable options                    |
| A7    | §1.2 per-combination quantity              | v1       | Per-variant quantity                                                 |
| A8    | §2.2 walnut-table 136-cell matrix          | partial  | Seller flow lists bulk actions by axis value; no flat-amount sweep   |
| A9    | §2 per-option price ranges                 | v1       | Signed delta shown at point of choice                                |
| A10   | §2 price-range-until-selected              | v1       | Defaults preselected; itemized price panel                           |
| A11   | §1.2 sku_on_property                       | v1       | Per-variant SKU; order snapshot of the configuration                 |
| A12   | §2.2 pet-portrait case                     | partial  | Axes cover it; digital-delivery half is deferred (§9)                |
| B1    | §1.3 char limit                            | v1       | Text modifier `char_limit`                                           |
| B2    | §1.3 add-on pricing                        | v1       | Modifier `add_on_price_cents`                                        |
| B3    | §2.2 wedding paper-stock dropdown          | v1       | Select modifier with per-option price                                |
| B4    | §2.1 personalization dropdowns             | v1       | Select modifier                                                      |
| B5    | §1.3 per-question required                 | v1       | Per-modifier `required`                                              |
| B6    | §2.2 blank-mug case                        | v1       | Modifier scopes                                                      |
| B7    | §3.2 measurement modifier                  | v1       | Measurement modifier with per-unit rate                              |
| B8    | §2.2 pet-portrait Messages handoff         | deferred | §9 — no `file_upload` modifier kind                                  |
| B9    | §1.3 answers on receipt                    | v1       | Answers snapshot onto the order line                                 |
| B10   | §2.2 fonts-in-photo-gallery                | gap      | §2.2 note: no media on option/modifier options in v1                 |
| C1    | §2.2 candlesticks case                     | partial  | Units with label/condition/specs/price; no per-unit photos in v1     |
| C2    | §2.2 candlesticks case                     | v1       | Unit flips to sold in `PlaceOrder`; no cart-time reservation (§9)    |
| C3    | §2.2 wedding quantity tiers                | v1       | Quantity breaks                                                      |
| C4    | §2 bead-lot "price is for 100 grams"       | gap      | No unit-of-sale concept; description prose remains                   |
| C5    | §1.4 per-axis readiness                    | gap      | No fulfillment profile in this prototype's schema                    |
| C6    | §2.2 walnut-table case                     | partial  | Linear measurement rate; area-proportional formula deferred (§9)     |
| C7    | §2.2 walnut-table in-between sizes         | partial  | Measurement modifier covers linear cases                             |
| C8    | §2.2 pet-portrait "Print file"             | deferred | §9 — no per-variant digital delivery                                 |
| C9    | §2.2 blank-mug case                        | v1       | Product-type value on an axis + scoped modifier                      |
| C10   | §2.2 "Preet Custom Order"                  | deferred | §9 — no private-quote object                                         |
| C11   | §2.1 unit/made-to-order/batch stock        | partial  | Units vs variant quantity; made-to-order state not modeled           |
| D1    | §2 emoji pseudo-headers                    | v1       | Typed description sections                                           |
| D2    | §2 How-to-Order blocks                     | partial  | No `how_to_order` kind; authored as a text section, not auto-placed  |
| D3    | §2 pasted size charts                      | v1       | `size_chart` section kind                                            |
| D4    | §2 repeated disclaimers                    | gap      | Sections are per-listing; no shared/shop-level snippet               |
| D5    | §1.1 taxonomy-gated setup                  | v1       | Category picker gates offered properties                             |
| D6    | §1.1 closest-match + tags workaround       | gap      | Property values are enumerated; no seller-added value                |
| D7    | §2 digital file manifests                  | deferred | §9 — no digital assets                                               |
| D8    | §1.4 digital no-returns                    | deferred | §9 — digital delivery out of scope; disclaimer section is manual     |
| E1    | §1.2 full-replace PUT risk                 | v1       | Order-line snapshots freeze configuration and price breakdown        |
| E2    | §6 publish gates                           | v1       | Publish refusal lists every issue, linked to the owning screen       |
| E3    | §2.1 linked add-on listings                | deferred | §9 — add-ons stay separate listings                                  |

## Working

2026-08-27 — Outcome 1 delivered: design canvas published for human review at
https://claude.ai/code/artifact/2d93c687-00a7-494f-9e07-7afaaaf9eef5
(11 artboards, 2 pages). Implementation (Outcome 2) waits on that review.

Design decisions the canvas commits to:

- One listing editor page organized by seller intent; sub-screens per section.
  Section names: Your item · Choices you offer · Combinations & stock ·
  Questions you ask the buyer · Individual pieces · Quantity discounts ·
  Listing page sections · Before this can go live.
- Progressive disclosure: an unconfigured listing shows only "Your item" plus
  five one-line invitations; opening a section is what reveals its machinery.
  The `Main` artboard (simple print listing) and `GrownListing` artboard
  (configured mug) show the two lives of the same page.
- Vocabulary map (UI ↔ schema, schema unchanged): choice ↔ option_axis ·
  option's price difference ↔ surcharge_cents · combination ↔ variant ·
  "you don't make this" ↔ enabled=false · individual piece ↔ unit ·
  question ↔ modifier · "only ask when…" ↔ modifier_scopes · quantity
  discount (% off) ↔ quantity_break (bps internally) · page section ↔
  description_section · item fact ↔ listing_attribute.
- Every editing surface carries a "What buyers see" panel rendered in the
  storefront's own styling; the Questions artboard shows the scoped question
  present for Hand-lettered and absent for Blank, plus the answer landing on
  the order line (B9). Human review 2026-08-27 confirmed this panel as a
  must-keep; implementation renders it from the same support path the shop
  listing page uses, so the preview cannot drift from the real buyer view.
- Deferred/gap stories get visible honest placements ("coming — not in this
  version" / "not yet" notes at the point a seller would look): B8 photo-type
  card, B10 preview-image squares, A8 flat-amount sweep row, C4 weight note,
  C5 timeline note, C8/D7/D8 physical-goods-only line, C10 private-quote
  note, D4 shared-snippet note, D6 custom-fact-value note, D2 pin-beside-
  choices placement, C1 per-piece photo slot.
- Structured measurement rows replace the Units screen's raw "Specs (JSON)"
  textarea; quantity discounts read in percent, never basis points; publish
  checklist items name the fix and link to the owning field (E2).
- Page 2 of the canvas is the story-by-story coverage table (all 44 —
  the ticket prose above says "43 stories" but the embedded list
  enumerates 44: A1–A12, B1–B10, C1–C11, D1–D8, E1–E3),
  ships-now / partly-ships / designed-not-shipped / honest-note per story —
  the implementation-phase validation checklist.

Canvas working files: session scratchpad `dsgn-001/` (re-extractable from the
artifact via the design tooling if the session's files are gone).

### Implementation record (2026-08-27)

Shipped across five commits on php/item-configurator: 6a179ef (hub, buyer-view
component, publish presenter), 281fc48 (choices, pieces), 323eebc
(combinations & stock, questions), dc99271 (discounts, page sections),
25fb7b1 (shop-page sections, preselect exclusivity, story sweep). Suite:
2547 tests, 7251 assertions, 100% lines; `make check` green throughout.
Actions and Domain untouched; new logic in app/Support/Configurator/*,
app/Http/Requests/Seller/* transforms, views, and the seller BuyerView
component. JS-off holds on every screen (type-first flows and expanded
edit rows ride GET params).

Validation (Outcome 3): every v1/partial story carries a story-named feature
test; deferred/gap stories carry a render test on their honest slot. Mapping:

| Story | Status | Test |
| --- | --- | --- |
| A1, A2, A4, A9, A12, C5 | ships / partly / note | OptionAxisControllerTest (story-named) |
| A3, A5, A6, A7, A8, A11, C11 | ships / partly | VariantControllerTest (story-named) |
| A10 | ships | Shop/ListingControllerTest `A10: preselects…` |
| B1, B2, B3+B4, B5, B6, B7, B8+B10+E3 | ships / notes | ModifierControllerTest (+BuyerViewTest) |
| B9 | ships | OrderControllerTest `B9: shows an answered question…` |
| C1, C2, C4 | partly / ships / note | UnitControllerTest (story-named) |
| C3, C10 | ships / note | QuantityBreakControllerTest (+BuyerViewTest) |
| C6, C7 | partly | Shop/CartControllerTest (story-named) |
| C8, D6, D7, D8 | honest notes | Seller/ListingControllerTest (footer/hint tests) |
| C9 | ships | ModifierScopeControllerTest `C9: …` |
| D1, D3 | ships | DescriptionSectionControllerTest + Shop/ListingControllerTest |
| D2, D4 | partly / note | DescriptionSectionControllerTest (story-named) |
| D5, E1, E2 | ships | Seller/ListingControllerTest (story-named + issue-code tests) |

Accepted decisions (design-level, recorded rather than built): no
"See it as buyers will" header link (buyer panels carry the consequence);
choice/option ordering is append-only with no reorder control (mock shows
none); the custom-choice add form collapses on a validation error; a legacy
non-serialized empty combination blocking "Start listing pieces" is a
documented no-UI state; question kind stays changeable via a craft-worded
select.

Follow-ups filed from the work: BUG-007 (a priced question on a choice-free
listing never charges — pre-existing cart gate, found by the sweep),
IMPRV-012 (coming-pill component; preselect control semantics).
