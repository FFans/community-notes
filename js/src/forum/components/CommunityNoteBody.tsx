import Component from 'flarum/common/Component';

import CommunityNote from '../models/CommunityNote';
import CommunityNoteSources from './CommunityNoteSources';

export default class CommunityNoteBody extends Component<{ note: CommunityNote; className: string }> {
  view() {
    const note = this.attrs.note;

    return (
      <div className={'CommunityNote-body ' + this.attrs.className}>
        <p className="CommunityNote-content">{note.content()}</p>
        <CommunityNoteSources sources={note.sources() || []} />
      </div>
    );
  }
}
