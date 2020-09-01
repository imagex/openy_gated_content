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
      :category="'12bb1869-f9c4-4198-ae77-f4a634b57376'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />
    <VideoListing
      :title="'Conditioning Workouts'"
      :category="'57f8f0d2-4884-444d-9bb4-02719a95dc87'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />
    <VideoListing
      :title="'Balance Workouts'"
      :category="'7b436669-1450-4e78-bfb2-5d41a1cbf08e'"
      :viewAll="true"
      :limit="3"
      :featured="true"
    />

    <BlogListing :title="'Y Plus'" :featured="false" :viewAll="true" :limit="6"/>
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
