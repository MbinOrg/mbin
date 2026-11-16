# Fedi Custom CSS

*Fedi Custom CSS* is an inofficial extension to Actors to allow them customizing how others see their profiles and content.
It works by users and groups (Magazines / Communities) submitting CSS code for a couple of special properties of their Actor,
an URL to load this code and new properties in the Actor object containing these URLs.

There are currently 3 types of Custom CSS:
- **Profile CSS:** applies to the profile page of an user
- **Content CSS:** applies to a users content (Entries, Posts, Comments) on *all* pages
- **Magazine CSS:** applies to a Magazine (aka Communities) and all its content when viewed inside the Magazine (so not on the "/all" feed for example);
  can be overridden by Content CSS of a user

Every platform is required to offer settings to their users to disable (or enable) such styles.

The following sections describe rules for the CSS code, the federation and the application of custom styles.

## CSS rules

These rules give a baseline what CSS is allowed and what is not for the different types.
Every platform can have their own rules, but this can introduce incompatibilities with other platforms. \
When the CSS of one Actor property violates a rule, the whole CSS should be ignored and the property treated as unset.

### Rules for Profiles and Magazines

As Profiles and Magazines are only visible when a user actively opens them, the rules are very lenient.

- max 512 KiB in size
- no special declarations (at-rules), except `@keyframes`
- no special rules (only rules commonly used to configure the look and feel)

### Rules for User Content

As content of random users can show up without the viewer actively opening it, the rules are more string and
require a confinement on only the HTML elements representing the authors content.

- max 128 KiB
- no special declarations (at-rules), except `@keyframes`
- no special rules (only rules commonly used to configure the look and feel)
- `*` is nowhere allowed in a selector
- `url()` values may only use inline data (starting with `data:image/`)
- blocks inside a `@keyframes` may only use percentages as their selectors

Most importantly, selectors and `@keyframes` names have to be prefixed with a special value to confine the rules to the users content.
The next section describes how these prefixes have to be constructed and validated.

- Every selector of a declaration block needs to start with the full *fediCustomCss* selector, followed directly
  (no descendant operator, child operator, ...) by another class (specified by the user).
  After that the user can specify whatever selector they want (except `*`).
- Names of every `@keyframes` block must start with the *user* component of the users *fediCustomCss* selector (with no dot),
  followed by a `-` and then the given name of the keyframes (which must not be empty).

### The fediCustomCss selector

The selector consist of three parts:
1. The general component: `.fediCustomCss`
2. The software component: `.fediCustomCss-software-<software_name>`
3. The user component: `.fediCustomCss-user-<encoded_user_id>`

`<software_name>` is the name of the software the rule targets. It is used to allow defining rules fitting the different UIs of different Fedi-Platforms.
It must match the following Regex: `[a-z0-9_]{3,64}`, but should not start or end with an underscore.

`<encoded_user_id>` is the Actors ID (e.g. the absolute URL to a Users ActivityPub object), encoded as Base64
with the following character substitutions: '+' -> '-', '/' -> '_', '=' removed.

A rule selector prefix is build by concatenating all component. \
Example valid rule selector for a user with the ID `https://remote.local/u/test`:
```css
.fediCustomCss.fediCustomCss-software-mbin.fediCustomCss-user-aHR0cHM6Ly9yZW1vdGUubG9jYWwvdS90ZXN0.classA .classB {}
```

Classes for HTML elements are build by adding all components to the class-list of the element.
Example element for a user with the ID `https://remote.local/u/test`:
```html
<div class="classA classZ fediCustomCss fediCustomCss-software-mbin fediCustomCss-user-aHR0cHM6Ly9yZW1vdGUubG9jYWwvdS90ZXN0"></div>
```

Names for keyframes are build by concatenating the user component, '-', and the given name.
Example valid keyframes declaration for a user with the ID `https://remote.local/u/test`:
```css
@keyframes fediCustomCss-user-aHR0cHM6Ly9yZW1vdGUubG9jYWwvdS90ZXN0-myAnimation {}
```

## Federation

All Custom CSS types are federated. They use the following properties of the Actor object:
- Profile CSS: `_fedi_custom_css_profile` and `_wafrn_customCSS` (for Wafrn compatibility)
- Content CSS: `_fedi_custom_css_content`
- Magazine CSS: `_fedi_custom_css_community`

The value of these properties must be a string. If the property is unset, `null` or an empty string, the custom CSS must bre removed from the owner.

The value may be the CSS code. In this case needs to be validated and (if valid) stored in the internal representation of the owner
and added to the relevant pages. \
The value may be an URL pointing to the CSS code. Differentiation between raw CSS and URL is done by checking if the value starts with
`https://` or `http://` (allowing http is optional).
In case of a URL value, the receiving software must download the CSS code and validate it. If it is valid it can either
store the code directly in its internal representation of the owner or store the URL +
a computed [integrity hash](https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Subresource_Integrity).

For federating Custom CSS the instance sends an Update activity of the Actor object with the relevant properties set.
The value is a string with either the CSS code or a URL pointing to it. Using a URL is preferred to save resources.
If the User or Magazine has no Custom CSS set (anymore) the properties shall be omitted from the Actor object.

## Application of styles

Custom CSS styles are included in every page the software sees relevant. \
If the stored CSS is CSS code, it is included with `<style>` blocks in the document head or assembled into one CSS document
(which may also include styles of other sources) and included with a `<link>` element.
If the stored CSS is an URL, it is included with a `<link>` element referencing the URL, setting the *integrity hash*
and using `crossorigin="anonymous"`.

Example for a URL value:
```html
<link rel="stylesheet" href="<the url>" integrity="<the integrity hash>" crossorigin="anonymous" referrerpolicy="same-origin" >
```

For **Content CSS** every HTML element containing user content needs to set the classes as described in [The fediCustomCss selector](#the-fedicustomcss-selector).
Only the other most container element of an individual pice of content needs to set these classes.
