<template>
  <div class="video-teaser">
    <router-link :to="{ name: 'Video', params: { id: video.id } }">
        <div class="preview" v-bind:style="{
              backgroundImage: `url(${image})`
            }">
          <YoutubePlayButton></YoutubePlayButton>
          <div v-if="duration" class="duration">{{duration}}</div>
        </div>
        <div class="title">{{ video.attributes.title }}</div>
        <div
          v-if="category"
          class="meta">
          <div class="video-level">
            {{ category | first_letter }}
          </div>
          {{ category | capitalize }}
        </div>
    </router-link>
  </div>
</template>

<script>
import YoutubePlayButton from '@/components/YoutubePlayButton.vue';

export default {
  name: 'VideoTeaser',
  components: {
    YoutubePlayButton,
  },
  props: {
    video: {
      type: Object,
      required: true,
    },
  },
  computed: {
    category() {
      if (this.video.attributes.field_gc_video_category.length > 0) {
        return this.video.attributes.field_gc_video_category[0].name;
      }

      return '';
    },
    image() {
      if (this.video.attributes['field_gc_video_image.field_media_image']) {
        return this.video.attributes['field_gc_video_image.field_media_image']
          .image_style_uri[0].gated_content_teaser;
      }

      if (!this.video.attributes['field_gc_video_media.thumbnail']) {
        return null;
      }

      return this.video.attributes['field_gc_video_media.thumbnail'].image_style_uri[0].gated_content_teaser;
    },
    duration() {
      const sec = this.video.attributes.field_gc_video_duration;
      if (sec === null) {
        return '';
      }

      function appendZero(n) {
        return (n < 10) ? `0${n}` : n;
      }

      return `${appendZero(Math.floor(sec / 60))}:${appendZero(sec % 60)}`;
    },
  },
};
</script>
