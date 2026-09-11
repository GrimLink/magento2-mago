# Privacy mode V2 plan (issue #97)

V1 (see `privacy-mode.md`) keeps customer PII out of what the assistant sends to the LLM on every
egress path, with classification held centrally in `PiiClassificationRegistry` plus an opt-in
`FieldClassifierInterface`, and a **lenient default**: a tool that declares nothing passes through
(the heuristic still catches email/phone/IBAN/BSN/VAT, but a name or a bare id it returns reaches the
LLM). V2 closes that gap by making classification mandatory and flipping the default to fail-closed.

The two pieces below are deliberately deferred from V1 because together they are a **major, breaking
release (2.0.0)**. They should ship as one coordinated change, not piecemeal.

---

## 1. Classification becomes a required method on the `@api` interface (decision 8)

**What.** Move field classification from the central registry / opt-in interface onto the tool itself
as a *required* method. Every action declares how its own output fields cross to the LLM.

- Add to `Api\Skill\ActionInterface` (and `Api\Tool\ToolInterface` for flat tools):
  ```php
  /** @return array<string,array{0:string,1?:string}> field => [PiiClass, tokenType?] */
  public function getFieldClassification(): array;
  ```
- `PiiClassificationRegistry` stops holding built-in maps; it reads each action's declaration.
- The opt-in `FieldClassifierInterface` (V1) folds into this required method and is removed.

**Cost / blast radius.** This is a hard `@api` break:
- **~61 action classes** implement `ActionInterface` and must each add the method (the ~9 PII actions
  return their real map; the ~52 PII-free actions return `[]`, which under the new default means
  "everything public" only for the fields they name, see §2 on how PII-free tools stay usable).
- `AbstractSkill` and any base action class.
- **Every third-party tool** breaks (fatal on the missing method) until updated, this is why it is a
  major version and must be announced with a migration note.

**Migration path for integrators.** Publish before the release: "implement `getFieldClassification()`
on your actions; return `[]` for a tool that returns no personal data; classify every field that can
carry a name, email, phone, address, review text or a person-linkable id." Ship a short cookbook with
the four canonical shapes (customer lookup, order lookup, list-of-ids, aggregate).

**Version.** 1.x → **2.0.0**. Update `composer.json`, the changelog, and the module `setup_version`
expectations.

---

## 2. Hard fail-closed default (strip unless declared)

**What.** Flip `PrivacyFilter`'s `$stripUnclassified` from `false` (V1 lenient) to `true`: any scalar
field an action does not declare is **stripped**, for every tool. Undeclared is never public.

**Why it waits for §1.** With the lenient default off and no universal declaration, the PII-free tools
(product_data, cms_data, aggregates, docs_search, config, cache/indexer/navigation, the bulk of the
~52) would have their output stripped to nothing and the assistant would break for most queries. So
the hard default can only flip **after** every tool declares its fields (§1). The two are one change.

**Concrete effect once both land.**
- A tool that returns `[]` from `getFieldClassification()` and emits fields → those fields are stripped
  (so a PII-free tool must still declare its public fields as `PiiClass::PUBLIC`, or return them under
  a declared-public map). Decide during V2 whether "declares `[]`" means "strip all" (strict) or
  "the tool asserts it is PII-free, pass scalars", recommend strict + a `public`-all helper for
  genuinely public tools, so nothing is public by omission.
- A brand-new or third-party tool that forgets to classify leaks **nothing**, it fails closed.
- The `docs/privacy-mode.md` "unclassified tool leaks names/ids" warning is removed; it no longer
  applies.

**Keep from V1 (do not regress).** The always-rules survive the flip: `admin_url` always stripped, the
`error`/`message` envelope always kept (and heuristic-rescrubbed), the heuristic pass over every kept
string, forged-token neutralisation, the write-token refusal, and the persistent vault.

---

## V2 checklist

- [ ] Add `getFieldClassification()` to `ActionInterface` + `ToolInterface` (@api).
- [ ] Implement it on all ~61 actions (real maps for the PII tools, explicit public maps for PII-free
      tools; a `PublicFieldsTrait`/helper to make the PII-free ones one line).
- [ ] Rewrite `PiiClassificationRegistry` to read declarations from the tools; delete the built-in
      const map and the opt-in `FieldClassifierInterface`.
- [ ] Flip `PrivacyFilter` to `$stripUnclassified = true` (via di.xml, so it is auditable).
- [ ] Decide the "`[]` declaration" semantics (recommend strict-strip + explicit public helper).
- [ ] Update every unit test that constructs the registry/filter for the new source of truth.
- [ ] Bump to 2.0.0; write the changelog + integrator migration note + the four-shape cookbook.
- [ ] Remove the V1 "unclassified leaks" warning from `privacy-mode.md`.

## Not part of V2 (separate tickets)

- ConfigReader denylist → allowlist (#106)
- admin_url / navigator `url` secret key kept out of the payload, re-attached UI-side (#107)
- Conversation/vault retention + right-to-erasure (#108)
