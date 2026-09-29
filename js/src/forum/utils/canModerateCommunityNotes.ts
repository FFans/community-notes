import app from 'flarum/forum/app';

export default function canModerateCommunityNotes(): boolean {
  return !!app.session.user && app.forum.canModerateCommunityNotes();
}
