<script setup>
import { RouterView } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
</script>

<template>
  <VApp>
    <RouterView v-if="$route.name === 'login'" />
    <DefaultLayout v-else />
    <ConfirmDialog />
  </VApp>
</template>
