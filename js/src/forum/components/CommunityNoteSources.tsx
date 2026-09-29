import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';

import { safeSource } from '../utils/noteInput';

export default class CommunityNoteSources extends Component<{ sources: string[] }> {
  view() {
    if (!this.attrs.sources.length) return null;

    return (
      <div className="CommunityNoteSources">
        <ul aria-label={app.translator.trans('ffans-community-notes.forum.sources.a11y_label', {}, true)}>
          {this.attrs.sources.map((url, index) => (
            <li key={index}>
              {safeSource(url) ? (
                <a href={url} target="_blank" rel="noopener noreferrer">
                  {url}
                  <span className="sr-only">
                    {app.translator.trans('ffans-community-notes.forum.sources.open_in_new_window_a11y_label')}
                  </span>
                </a>
              ) : (
                <span>{url}</span>
              )}
            </li>
          ))}
        </ul>
      </div>
    );
  }
}
