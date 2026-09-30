<script setup>
import { RouterView } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
</script>

<template>
  <RouterView v-if="$route.name === 'login'" />
  <DefaultLayout v-else />
</template>
