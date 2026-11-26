# Custom Styles

With Custom Styles you can personalize how you see the Web UI and how others see your profile and content.
Furthermore, it is possible to add styles to Magazines you own.

In your account settings you can disable the 3 different types of custom styles. Then they will not be applied in your Web UI.

## Custom Page CSS

With this setting you can customize your visual experience of the whole UI.

- The CSS entered here will be included on every page.
- It is only visible to you.
- There are no restrictions on the CSS.

## Public Profile CSS

This allows you to customize your profile page (`/u/your_name`) and how other users see you.
So you can get creative and express yourself to whoever visits your profile.

- The CSS entered here will be included on your profile page.
- It is visible to everyone.
- It is federated.
- The CSS must be compliant to the [rules](#profile-css)

## Public Content CSS

Content CSS applies to *content* (Entries, Posts, comments) and pages where your content shows up
(front page, Combined view, Micoblog View, Comments of an Entry or Post, ...) will apply it.
You can make your content stand out, add simple elements and animations or simply set the background to your favourite color.

- The CSS entered here will be included on every page which shows your content and only effects the HTML elements of your content.
- It is visible to everyone.
- It is federated.
- The CSS must be compliant to the [rules](#content-css)

## Magazine CSS

This allows you to customize the style of a Magazine. It is visible on its frontpage and every content of posted in it.
So you can adjust the looks of the Magazine to its topic or just do what you think is pretty.

- The CSS entered here will be included on every page of the Magazine.
- It is visible to everyone.
- It is federated.
- The CSS must be compliant to the [rules](#profile-css)

## Rules

To limit abuse of the feature the submitted CSS is validated against a set of rules.
If your CSS violates one, all of it will be rejected.

### Profile CSS

- max 512 KiB
- no special declarations (at-rules)
- `@keyframes` is allowed

### Content CSS

- max 128 KiB
- no special declarations (at-rules)
- `@keyframes` is allowed
- all selectors must start with a class; `*` is nowhere allowed
- `url()` values may only use inline data (starting with `data:image/`), to use images you have to encode them for CSS url() values

Also, to restrict the effect of your styles to your content, every selector must be prefixed with a special selector.
For more information see [here](../05-fediverse_developers/02-custom_css.md).
You can omit this prefix; it will be added automatically to every selector. \
Names of `@keyframes` must also be prefixed. Here you only need the *user* component of the Fedi Custom CSS selector (without a dot),
followed by a `-` and then your chosen name for the keyframes.
You can omit the prefix as it will be automatically be added too. But be aware that this only applies to `@keyframes` names,
you have to insert the full names in `animation` rules yourself.
