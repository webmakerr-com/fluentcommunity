import Members from './Members.vue';
import LineChart from './LineChart.vue';
import DiscussLine from '@/images/icons/DiscussLine.vue';
import StickyNoteLine from '@/images/icons/StickyNoteLine.vue';
import ThumbsUpLine from '@/images/icons/ThumbsUpLine.vue';

import { markRaw } from 'vue'

export const icons = {
  Members: markRaw(Members),
  LineChart: markRaw(LineChart),
  DiscussLine: markRaw(DiscussLine),
  StickyNoteLine: markRaw(StickyNoteLine),
  ThumbsUpLine: markRaw(ThumbsUpLine),
}
