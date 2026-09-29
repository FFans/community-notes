import app from 'flarum/forum/app';

import CommunityNoteEditorModal from './components/CommunityNoteEditorModal';
import CommunityNotesPostModal from './components/CommunityNotesPostModal';
import addIndexSidebar from './extenders/addIndexSidebar';
import addPostControls from './extenders/addPostControls';
import addPublicNote from './extenders/addPublicNote';
import CommunityNote from './models/CommunityNote';
import { requestError } from './utils/noteInput';

export { default as extend } from './extend';

app.initializers.add('ffans-community-notes', () => {
  addPublicNote();
  addIndexSidebar();

  const opening = new Set<string>();

  addPostControls(
    async (post) => {
      const id = post.id()!;

      if (opening.has(id)) return;

      opening.add(id);

      try {
        const ownId = post.attribute<number | null>('myCommunityNoteId');
        const note = ownId ? await app.store.find<CommunityNote>('community-notes', String(ownId)) : undefined;
        app.modal.show(CommunityNoteEditorModal, { post, note });
      } catch (error) {
        app.alerts.show({ type: 'error' }, requestError(error));
      } finally {
        opening.delete(id);
        m.redraw();
      }
    },
    (post) => app.modal.show(CommunityNotesPostModal, { post })
  );
});
