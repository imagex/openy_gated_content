<template>
  <div class="home">
    <EventListing
      :title="'Today\'s classes'"
      :only-today-filter="true"
      :viewAll="true"
      :limit="3"
      :msg="'No classes today.'"
    />
    <VideoListing
      :title="'Strength Workouts'"
      :category="'8ac488de-6963-4e18-b524-8781d502547e'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />
    <VideoListing
      :title="'Conditioning Workouts'"
      :category="'739dcf3f-8f91-45de-b72a-46d15e60142c'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />
    <VideoListing
      :title="'Balance Workouts'"
      :category="'c878a126-d39d-484d-9023-1561816f193b'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />

    <BlogListing :title="'More info'" :featured="false" :viewAll="true" :limit="6"/>
  </div>
</template>

<script>
import BlogListing from '@/components/blog/BlogListing.vue';
import VideoListing from '@/components/video/VideoListing.vue';
import EventListing from '@/components/event/EventListing.vue';

export default {
  name: 'Home',
  components: {
    BlogListing,
    VideoListing,
    EventListing,
  },
};
</script>
