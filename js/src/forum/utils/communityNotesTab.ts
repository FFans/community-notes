import type { FeedTab } from '../states/CommunityNotesState';

export default function communityNotesTab(): FeedTab {
  const tab = m.route.param('tab');
  return tab === 'latest' || tab === 'hidden' ? tab : 'helpful';
}
