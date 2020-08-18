<template>
  <div class="live-stream-listing-page">
    <EventListing
      :title="'Classes'"
      :withDateFilter="true"
      :msg="'No classes scheduled for this date.'"
    />
  </div>
</template>

<script>
import EventListing from '@/components/event/EventListing.vue';

export default {
  name: 'LiveStreamListingPage',
  components: {
    EventListing,
  },
};
</script>
