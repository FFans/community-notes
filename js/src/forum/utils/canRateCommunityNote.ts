import app from 'flarum/forum/app';

import type CommunityNote from '../models/CommunityNote';

export default function canRateCommunityNote(note: CommunityNote): boolean {
  if (!app.session.user || !app.forum.canRateCommunityNotes() || note.isMine() || note.isHidden()) return false;
  const post = note.post();
  return !post || (!post.isHidden() && post.contentType() === 'comment');
}
