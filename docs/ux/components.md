# Components and theme

How the theme of `docs/ux/brand.md` is built (S-0910) and which base components exist. Use these before writing new ones.

## Theme

| File | Content |
|---|---|
| `resources/css/tokens.css` | Brand constants, roles for light and dark, and the names components use (`--background`, `--primary` and so on), which only point to roles. |
| `resources/css/app.css` | Fonts, Tailwind theme, base rules: headings, tabular numbers, Arabic script, reduced motion. |
| `resources/js/theme/tokens.test.ts` | Contrast of both modes (4.5:1 text, 3:1 controls, focus and charts), no palette colors in components, self-hosted fonts. |

- **White label:** all defaults are in the cascade layer `theme`. Any unlayered rule wins, such as the brand color of a tenant in the page head. Swap token values, never component classes.
- **Colors in components:** only token classes (`bg-card`, `text-muted-foreground`, `text-success`). Palette classes such as `text-red-600` fail the test.
- **Dark mode:** the class `dark` on `html`, set before first paint. Choice per user under Settings > Appearance.
- **Fonts:** Fontsource packages, copied into the build as WOFF2. A browser loads only the subsets a page needs.
- **Text sizes:** the Tailwind names stay. `text-xs` is 12 px, `text-sm` 14 px (the base of dense views in brand.md), `text-base` 16 px (reading views). Headings use Mona Sans through the base rules.
- **Arabic script:** one size larger, line height 1.6, no letter-spacing.
- **Numbers:** tables, `time` and `.pt-num` use tabular numbers.
- **Focus:** a full-strength ring; half transparent it falls below 3:1.

## Base components (`resources/js/components`)

| Component | Use |
|---|---|
| `AppSidebar`, `AppSidebarHeader`, `Breadcrumbs`, `NavUser` | App shell: sidebar, top bar with breadcrumbs, user menu |
| `Heading` | Page or section title with description |
| `EmptyState` | Nothing to show yet: `title`, optional `description` and `icon`, the one action in the slot |
| `InputError` | Validation message under a field |
| `ui/button` | Variants default, secondary, outline, ghost, destructive, link. One default button per view region |
| `ui/input`, `ui/select`, `ui/checkbox`, `ui/label`, `ui/input-otp` | Form controls |
| `ui/table` | `Table`, `TableHeader`, `TableBody`, `TableRow`, `TableHead`, `TableCell`; `numeric` aligns amounts and hours to the end of the line |
| `ui/dialog`, `ui/sheet`, `ui/dropdown-menu`, `ui/tooltip` | Overlays |
| `ui/sonner` | Toasts |
| `ui/alert`, `ui/badge`, `ui/card`, `ui/skeleton`, `ui/spinner` | Status and layout |

```vue
<Table :label="$t('...')">
    <TableHeader>
        <TableRow>
            <TableHead>{{ $t('...') }}</TableHead>
            <TableHead numeric>{{ $t('...') }}</TableHead>
        </TableRow>
    </TableHeader>
    <TableBody>
        <TableRow v-for="row in rows" :key="row.id">
            <TableCell>{{ row.name }}</TableCell>
            <TableCell numeric>{{ row.hours }}</TableCell>
        </TableRow>
    </TableBody>
</Table>
```

## Open

- Official PROJEKTRA logo files (assumption A-02): the neutral mark stays until they arrive.
- The Beam gradient exists as a token (`bg-beam`) but no screen uses it yet.
- `prefers-contrast: more` and compact density are not built.
- A design system site (Storybook or Histoire, brand.md introduction) is not built; this page documents the components.
- The tables of Admin > Languages and Admin > Texts keep their own markup; new screens use `ui/table`.
