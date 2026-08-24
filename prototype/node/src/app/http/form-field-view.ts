/**
 * One text control's render data: what it shows, its error message (if any),
 * and the id `views/partials/field-error.ejs` gives that message — what
 * `views/partials/form-field.ejs` needs to wire a control's `aria-describedby`
 * and `aria-invalid`. A site's own view-model builder maps its domain fields
 * onto this shape field by field, so the template does no lookups of its own.
 */
export type FormFieldView = { value: string; error: string | null; errorId: string | null }

/**
 * `value` is what a submitted (or freshly loaded) field showed; `error` is
 * `undefined` when the field passed. `errorId` is `null` in that case too, so
 * a control with no error carries neither `aria-describedby` nor
 * `aria-invalid`.
 */
export function fieldView(id: string, value: string, error: string | undefined): FormFieldView {
  return { value, error: error ?? null, errorId: error === undefined ? null : `${id}-error` }
}
