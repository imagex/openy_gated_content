<template>
  <div class="blog-listing-page">
    <BlogListing
      :title="'More info'"
    />
  </div>
</template>

<script>
import BlogListing from '@/components/blog/BlogListing.vue';

export default {
  name: 'BlogListingPage',
  components: {
    BlogListing,
  },
};
</script>
