import app from 'flarum/forum/app';

export default function canViewCommunityNotes(): boolean {
  return !!app.session.user && (app.forum.canRateCommunityNotes() || app.forum.canModerateCommunityNotes());
}
