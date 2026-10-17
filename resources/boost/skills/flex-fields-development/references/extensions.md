# Custom composition and lifecycle

For a domain layout, first compose real existing fields with `CompositeField`
and `schema([...])`. Declaring `uses` requests dependencies; it does not render
child HTML or create its state, validation or authorization. Keep independent
asset boundaries for children that need them. `stylesFrom` reuses CSS without
starting that component's JavaScript. Optional capabilities are per instance.

For a distributed family, use the installed
`make:flex-component-package` command and public SDK. Extension builds may need
Node; normal consumers of prebuilt FLEX do not. Do not copy private runtime files
or mutate installed manifests from ordinary application boot.

Custom JavaScript uses `@bjanczak/flex-fields/sdk/v1` through the documented build
adapter. Register effects with `createResourceScope()` and dispose the owner's
scope on destruction. Explicitly declare custom surface activity and use its
readiness handle before presentation. Below-fold scrolling is not deactivation.

Closing a surface releases its interests; it does not necessarily destroy its
still-mounted factory. Actual unmount disposes registered effects. Do not cancel
another owner's pending shared import, remove a parent's resources when closing
a child modal or promise that evaluated JavaScript is unloaded.

Guides:
- https://flex-fields.bjanczak.com/docs/extensions/child-php-api
- https://flex-fields.bjanczak.com/docs/extensions/quickstart
- https://flex-fields.bjanczak.com/docs/extensions/sdk
- https://flex-fields.bjanczak.com/docs/extensions/surfaces
