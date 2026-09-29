import app from 'flarum/forum/app';

import CommunityNote from '../models/CommunityNote';
import { requestError } from '../utils/noteInput';
import { noteRequest } from '../utils/noteRequest';

export type FeedTab = 'helpful' | 'latest' | 'hidden';

export default class CommunityNotesState {
  notes: CommunityNote[] = [];
  loading = false;
  loaded = false;
  error = '';
  hasNext = false;
  scrollTop = 0;
  readonly limit = 20;
  private offset = 0;
  private requestId = 0;
  private failedLoadMore = false;

  constructor(
    readonly filter: { feed: Exclude<FeedTab, 'hidden'> } | { post: string } | { moderation: 'all' | 'hidden' }
  ) {}

  retry() {
    return this.load(this.failedLoadMore);
  }

  async load(more = false) {
    const requestId = ++this.requestId;
    const offset = more ? this.offset : 0;
    this.loading = true;
    this.error = '';

    try {
      const notes = await app.store.find<CommunityNote[]>('community-notes', {
        filter: this.filter,
        page: { offset, limit: this.limit },
        ...noteRequest,
      });

      if (requestId !== this.requestId) return;

      const identity = (note: CommunityNote) => {
        const post = 'feed' in this.filter && note.post();
        return post ? post.id() : note.id();
      };
      this.notes = more
        ? [
            ...this.notes,
            ...notes.filter((note) => !this.notes.some((existing) => identity(existing) === identity(note))),
          ]
        : notes;
      this.offset = offset + notes.length;
      this.hasNext = !!notes.payload.links?.next;
      this.loaded = true;
    } catch (error) {
      if (requestId === this.requestId) {
        this.error = requestError(error);
        this.failedLoadMore = more;
      }
    } finally {
      if (requestId === this.requestId) {
        this.loading = false;
        m.redraw();
      }
    }
  }
}
