import type Mithril from 'mithril';

import Alert from 'flarum/common/components/Alert';
import Button from 'flarum/common/components/Button';
import FormModal, { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Select from 'flarum/common/components/Select';
import Post from 'flarum/common/models/Post';
import app from 'flarum/forum/app';

import CommunityNote from '../models/CommunityNote';
import { noteReasons, requestError, validateNote } from '../utils/noteInput';

interface Attrs extends IFormModalAttrs {
  post: Post;
  note?: CommunityNote;
}

export default class CommunityNoteEditorModal extends FormModal<Attrs> {
  reason = '';
  text = '';
  sources = [''];
  error = '';

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    const note = this.attrs.note;
    if (note) {
      this.reason = note.reason();
      this.text = note.content();
      this.sources = [...note.sources()];
    }
  }

  canSave() {
    const note = this.attrs.note;

    return (
      !!app.session.user &&
      app.forum.canCreateCommunityNotes() &&
      !this.attrs.post.isHidden() &&
      (!note || (note.isMine() && !note.isHidden() && note.ratingCount() === 0))
    );
  }

  className() {
    return 'CommunityNoteEditorModal Modal--medium';
  }
  title(): Mithril.Children {
    return this.attrs.note
      ? app.translator.trans('ffans-community-notes.forum.editor.edit_title', {}, true)
      : app.translator.trans('ffans-community-notes.forum.editor.create_title', {}, true);
  }

  content() {
    return (
      <div className="Modal-body" aria-busy={this.loading}>
        <p className="helpText">{app.translator.trans('ffans-community-notes.forum.editor.help')}</p>

        {!this.canSave() && (
          <p className="helpText" role="status">
            {this.attrs.note
              ? app.translator.trans('ffans-community-notes.forum.editor.edit_unavailable', {}, true)
              : app.translator.trans('ffans-community-notes.forum.editor.create_unavailable', {}, true)}
          </p>
        )}

        <fieldset className="Form" disabled={this.loading || !this.canSave()}>
          <div className="Form-group">
            <label for="communityNoteReason">
              {app.translator.trans('ffans-community-notes.forum.editor.reason_label')}
            </label>
            <Select
              id="communityNoteReason"
              name="reason"
              options={{
                '': app.translator.trans('ffans-community-notes.forum.editor.reason_placeholder', {}, true),
                ...Object.fromEntries(
                  Object.entries(noteReasons).map(([reason, key]) => [reason, app.translator.trans(key, {}, true)])
                ),
              }}
              value={this.reason}
              onchange={(value: string) => {
                this.reason = value;
              }}
            />
          </div>

          <div className="Form-group">
            <label for="communityNoteContent">
              {app.translator.trans('ffans-community-notes.forum.editor.content_label')}
            </label>
            <textarea
              id="communityNoteContent"
              name="content"
              className="FormControl"
              rows="6"
              value={this.text}
              aria-describedby="communityNoteLength communityNoteContentHelp"
              oninput={(e: Event) => {
                this.text = (e.target as HTMLTextAreaElement).value;
              }}
            />
            <p id="communityNoteLength" className="helpText">
              {app.translator.trans('ffans-community-notes.forum.editor.character_count', {
                count: Array.from(this.text.trim()).length,
              })}
            </p>
            <p id="communityNoteContentHelp" className="helpText">
              {app.translator.trans('ffans-community-notes.forum.editor.content_help')}
            </p>
          </div>

          <div className="Form-group" role="group" aria-labelledby="communityNoteSourcesLabel">
            <label id="communityNoteSourcesLabel" for="communityNoteSource0">
              {app.translator.trans('ffans-community-notes.forum.editor.sources_label')}
            </label>
            <p id="communityNoteSourcesHelp" className="helpText">
              {app.translator.trans('ffans-community-notes.forum.editor.sources_help')}
            </p>
            {this.sources.map((url, index) => (
              <div className="CommunityNote-sourceRow">
                <input
                  id={`communityNoteSource${index}`}
                  name={`sources[${index}]`}
                  className="FormControl"
                  type="url"
                  aria-label={app.translator.trans(
                    'ffans-community-notes.forum.editor.source_a11y_label',
                    { number: index + 1 },
                    true
                  )}
                  aria-describedby="communityNoteSourcesHelp"
                  value={url}
                  placeholder="https://"
                  oninput={(e: Event) => {
                    this.sources[index] = (e.target as HTMLInputElement).value;
                  }}
                />
                <Button
                  className="Button Button--icon"
                  icon="fas fa-plus"
                  disabled={this.sources.length >= 5}
                  aria-label={app.translator.trans(
                    'ffans-community-notes.forum.editor.add_source_a11y_label',
                    { number: index + 1 },
                    true
                  )}
                  title={app.translator.trans('ffans-community-notes.forum.editor.add_source_button', {}, true)}
                  onclick={() => this.sources.splice(index + 1, 0, '')}
                />
                <Button
                  className="Button Button--icon"
                  icon="fas fa-minus"
                  disabled={this.sources.length === 1}
                  aria-label={app.translator.trans(
                    'ffans-community-notes.forum.editor.remove_source_a11y_label',
                    { number: index + 1 },
                    true
                  )}
                  title={app.translator.trans('ffans-community-notes.forum.editor.remove_source_button', {}, true)}
                  onclick={() => this.sources.splice(index, 1)}
                />
              </div>
            ))}
          </div>
        </fieldset>

        {this.error && (
          <Alert type="error" dismissible={false} role="alert">
            {this.error}
          </Alert>
        )}

        <div className="Form-group Form-controls">
          {this.canSave() && (
            <Button className="Button Button--primary" type="submit" loading={this.loading} disabled={this.loading}>
              {this.attrs.note
                ? app.translator.trans('ffans-community-notes.forum.editor.save_button', {}, true)
                : app.translator.trans('ffans-community-notes.forum.editor.submit_button', {}, true)}
            </Button>
          )}

          {this.attrs.note && this.canSave() && (
            <Button
              className="Button Button--link Button--danger"
              disabled={this.loading}
              onclick={() => this.deleteNote()}
            >
              {app.translator.trans('ffans-community-notes.forum.editor.delete_button')}
            </Button>
          )}
        </div>
      </div>
    );
  }

  async onsubmit(e: SubmitEvent) {
    e.preventDefault();
    if (this.loading || !this.canSave()) return;
    this.error = validateNote(this.reason, this.text, this.sources) || '';
    if (this.error) return;
    this.loading = true;
    try {
      const note = await (this.attrs.note || app.store.createRecord<CommunityNote>('community-notes')).save(
        {
          reason: this.reason,
          content: this.text.trim(),
          sources: this.sources.map((url) => url.trim()),
          ...(!this.attrs.note ? { relationships: { post: this.attrs.post } } : {}),
        },
        { params: { include: 'post.communityNote' } }
      );
      this.attrs.post.pushAttributes({ myCommunityNoteId: Number(note.id()) });
      app.alerts.show(
        { type: 'success' },
        this.attrs.note
          ? app.translator.trans('ffans-community-notes.forum.editor.updated_message', {}, true)
          : app.translator.trans('ffans-community-notes.forum.editor.created_message', {}, true)
      );
      this.hide();
    } catch (error) {
      this.error = requestError(error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  async deleteNote() {
    const note = this.attrs.note;
    if (
      this.loading ||
      !note ||
      !this.canSave() ||
      !window.confirm(app.translator.trans('ffans-community-notes.forum.editor.delete_confirmation', {}, true))
    )
      return;
    this.loading = true;
    this.error = '';
    try {
      await note.delete();
      this.attrs.post.pushAttributes({ myCommunityNoteId: null });
      // 删除后由服务端重新选择公开附注，同时更新管理入口的计数。
      void app.store.find('posts', this.attrs.post.id()!).catch(() => {});
      app.alerts.show(
        { type: 'success' },
        app.translator.trans('ffans-community-notes.forum.editor.deleted_message', {}, true)
      );
      this.hide();
    } catch (error) {
      this.error = requestError(error);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }
}
