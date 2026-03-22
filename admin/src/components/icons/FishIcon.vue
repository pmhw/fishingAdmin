<!-- 侧视鱼形图标（鱼尾 + 鱼身 + 鱼眼），用于回鱼条数等 -->
<template>
  <svg
    class="fish-icon"
    viewBox="0 0 24 24"
    fill="currentColor"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <!-- 鱼尾 -->
    <path d="M3 12 L8.5 6.5 L8.5 17.5 Z" />
    <!-- 鱼身与鱼头 -->
    <path
      d="M8.5 9.5 C8.5 7.8 10.2 6.5 12.8 6.5 C16.5 6.5 20 8.8 20 12 C20 15.2 16.5 17.5 12.8 17.5 C10.2 17.5 8.5 16.2 8.5 14.5 C8.5 13.5 8.5 12.5 8.5 11.5 C8.5 10.8 8.5 10.2 8.5 9.5 Z"
    />
    <!-- 鱼眼（略深于鱼身，在彩色底上仍清晰） -->
    <circle cx="16.2" cy="10.8" r="1.35" fill="rgba(15, 23, 42, 0.28)" />
  </svg>
</template>

<style scoped>
.fish-icon {
  width: 1em;
  height: 1em;
  display: block;
}
</style>
