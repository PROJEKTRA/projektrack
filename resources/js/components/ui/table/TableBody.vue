<template>
    <tbody
        data-slot="table-body"
        class="divide-y [&>tr]:border-border [&>tr:first-child]:border-t"
    >
        <slot />
    </tbody>
</template>
