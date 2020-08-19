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
      :category="'58d37870-df57-4dd3-895f-a2841c6bcd3b'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />
    <VideoListing
      :title="'Conditioning Workouts'"
      :category="'ac9d462b-a1c9-461c-abc0-e4a12ab25627'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />
    <VideoListing
      :title="'Balance Workouts'"
      :category="'bef01482-bd76-4c5b-b8f5-612996a0cb0e'"
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
