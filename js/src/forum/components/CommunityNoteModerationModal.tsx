import type Mithril from 'mithril';

import Alert from 'flarum/common/components/Alert';
import Button from 'flarum/common/components/Button';
import FormModal, { IFormModalAttrs } from 'flarum/common/components/FormModal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import app from 'flarum/forum/app';

import CommunityNote from '../models/CommunityNote';
import type CommunityNotesState from '../states/CommunityNotesState';
import type { FeedTab } from '../states/CommunityNotesState';
import canModerateCommunityNotes from '../utils/canModerateCommunityNotes';
import communityNotesTab from '../utils/communityNotesTab';
import { requestError } from '../utils/noteInput';
import { moderationRequest } from '../utils/noteRequest';
import CommunityNoteBody from './CommunityNoteBody';
import CommunityNoteMeta from './CommunityNoteMeta';

interface Attrs extends IFormModalAttrs {
  note: CommunityNote;
  onchanged?: () => void;
}

export default class CommunityNoteModerationModal extends FormModal<Attrs> {
  note?: CommunityNote;
  reason = '';
  error = '';
  success = '';

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    void this.load();
  }

  className() {
    return 'CommunityNoteModerationModal Modal--medium';
  }

  title() {
    return app.translator.trans('ffans-community-notes.forum.moderation.title', {}, true);
  }

  async load() {
    if (this.loading || !canModerateCommunityNotes()) return;
    this.loading = true;
    this.error = '';
    try {
      this.note = await app.store.find<CommunityNote>('community-notes', this.attrs.note.id()!, moderationRequest);
    } catch (error) {
      this.error = requestError(error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  content() {
    if (!canModerateCommunityNotes()) {
      return (
        <div className="Modal-body">
          <p className="CommunityNoteModerationModal-message">
            {app.translator.trans('ffans-community-notes.forum.moderation.permission_denied')}
          </p>
        </div>
      );
    }
    const note = this.note;

    return (
      <div className="Modal-body" aria-busy={this.loading}>
        {(this.error || this.success || !note) && (
          <div className="CommunityNoteModerationModal-message">
            {this.error && (
              <Alert type="error" dismissible={false} role="alert">
                {this.error}
              </Alert>
            )}
            {this.success && <p role="status">{this.success}</p>}
            {!note &&
              (this.loading ? (
                <LoadingIndicator display="block" />
              ) : (
                <Button className="Button" onclick={() => this.load()}>
                  {app.translator.trans('ffans-community-notes.forum.moderation.retry_button')}
                </Button>
              ))}
          </div>
        )}
        {note && (
          <>
            <CommunityNoteMeta note={note} />
            <CommunityNoteBody note={note} className="CommunityNoteModerationModal-note" />
            <div className="CommunityNoteModerationModal-form">
              {note.isHidden() ? (
                <div className="Form-group">
                  <strong>{app.translator.trans('ffans-community-notes.forum.moderation.hidden_reason_label')}</strong>
                  <p className="CommunityNoteModerationModal-reason">{note.hiddenReason()}</p>
                  <p className="helpText">
                    {app.translator.trans('ffans-community-notes.forum.moderation.restore_help')}
                  </p>
                </div>
              ) : (
                <div className="Form-group">
                  <label for="communityNoteHideReason">
                    {app.translator.trans('ffans-community-notes.forum.moderation.hide_reason_label')}
                  </label>
                  <textarea
                    id="communityNoteHideReason"
                    name="reason"
                    className="FormControl"
                    rows="3"
                    required
                    disabled={this.loading}
                    value={this.reason}
                    aria-describedby="communityNoteHideHelp"
                    oninput={(e: Event) => {
                      this.reason = (e.target as HTMLTextAreaElement).value;
                    }}
                  />
                  <p className="helpText" id="communityNoteHideHelp">
                    {app.translator.trans('ffans-community-notes.forum.moderation.hide_help')}
                  </p>
                </div>
              )}
            </div>
            <div className="CommunityNoteModerationModal-actions">
              <div className="CommunityNoteModerationModal-primaryActions">
                <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={this.loading}>
                  {note.isHidden()
                    ? app.translator.trans('ffans-community-notes.forum.moderation.restore_button', {}, true)
                    : app.translator.trans('ffans-community-notes.forum.moderation.hide_button', {}, true)}
                </Button>
                <Button className="Button Button--link" disabled={this.loading} onclick={() => this.hide()}>
                  {app.translator.trans('ffans-community-notes.forum.moderation.cancel_button')}
                </Button>
              </div>
              <Button
                className="Button Button--link CommunityNoteModerationModal-delete"
                disabled={this.loading}
                onclick={() => this.deleteNote()}
              >
                {app.translator.trans('ffans-community-notes.forum.moderation.delete_button')}
              </Button>
            </div>
          </>
        )}
      </div>
    );
  }

  async onsubmit(e: SubmitEvent) {
    e.preventDefault();
    const note = this.note;
    if (this.loading || !note || !canModerateCommunityNotes()) return;
    const restoring = note.isHidden();
    this.error = '';
    this.success = '';
    if (!restoring && !this.reason.trim()) {
      this.error = app.translator.trans('ffans-community-notes.forum.moderation.reason_required', {}, true);
      return;
    }
    this.loading = true;
    try {
      const payload = await app.request<ApiPayloadSingle>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/community-notes/${note.id()}/${restoring ? 'restore' : 'hide'}`,
        params: moderationRequest,
        body: restoring ? {} : { reason: this.reason.trim() },
      });
      this.note = app.store.pushPayload<CommunityNote>(payload);
      this.reason = '';
      this.success = restoring
        ? app.translator.trans('ffans-community-notes.forum.moderation.restored_message', {}, true)
        : app.translator.trans('ffans-community-notes.forum.moderation.hidden_message', {}, true);
      this.refreshFeed();
      this.attrs.onchanged?.();
      app.alerts.show({ type: 'success' }, this.success);
      this.hide();
    } catch (error) {
      this.error = requestError(error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  async deleteNote() {
    const note = this.note;
    if (this.loading || !note || !canModerateCommunityNotes()) return;
    if (!window.confirm(app.translator.trans('ffans-community-notes.forum.moderation.delete_confirmation', {}, true)))
      return;
    this.loading = true;
    this.error = '';
    this.success = '';
    try {
      const post = note.post();
      const displayed = post && post.communityNote();
      await note.delete();
      // 重新读取原帖，让公开附注关系切换到下一条符合条件的附注或清空。
      if (post) {
        if (post.attribute<number | null>('myCommunityNoteId') === Number(note.id())) {
          post.pushAttributes({ myCommunityNoteId: null });
        }
        if (displayed && displayed.id() === note.id()) {
          post.pushData({ relationships: { communityNote: null } });
        }
        void app.store.find('posts', post.id()!).catch(() => {});
      }
      this.refreshFeed();
      this.attrs.onchanged?.();
      app.alerts.show(
        { type: 'success' },
        app.translator.trans('ffans-community-notes.forum.moderation.deleted_message', {}, true)
      );
      this.hide();
    } catch (error) {
      this.error = requestError(error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  private refreshFeed() {
    const feed = app.cache.communityNotesFeed as Record<FeedTab, CommunityNotesState> | undefined;
    if (!feed) return;
    for (const tab of ['helpful', 'latest', 'hidden'] as const) feed[tab].loaded = false;
    // 帖子菜单的附注弹窗可能覆盖帖子流，同步刷新仍在显示的列表。
    if (feed && app.current.get('routeName') === 'communityNotes') void feed[communityNotesTab()].load();
  }
}
