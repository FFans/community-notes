import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import app from 'flarum/forum/app';

import CommunityNotesState, { FeedTab } from '../states/CommunityNotesState';
import canModerateCommunityNotes from '../utils/canModerateCommunityNotes';
import canViewCommunityNotes from '../utils/canViewCommunityNotes';

export default class CommunityNotesNavigation extends Component<{
  active: FeedTab;
  state: CommunityNotesState;
}> {
  view() {
    const { active, state } = this.attrs;
    return (
      <nav
        className="CommunityNotesPage-tabs"
        aria-label={app.translator.trans('ffans-community-notes.forum.navigation.list_a11y_label', {}, true)}
      >
        <div className="CommunityNotesPage-tabItems">
          {(['helpful', 'latest', ...(canModerateCommunityNotes() ? ['hidden'] : [])] as const).map((tab) => (
            <LinkButton
              className="CommunityNotesPage-tab"
              icon={tab === 'helpful' ? 'far fa-circle-check' : tab === 'latest' ? 'far fa-clock' : 'far fa-eye-slash'}
              href={app.route('communityNotes', tab === 'helpful' ? {} : { tab })}
              active={active === tab}
              aria-current={active === tab ? 'page' : undefined}
            >
              {tab === 'helpful'
                ? app.translator.trans('ffans-community-notes.forum.navigation.helpful_button', {}, true)
                : tab === 'latest'
                  ? app.translator.trans('ffans-community-notes.forum.navigation.latest_button', {}, true)
                  : app.translator.trans('ffans-community-notes.forum.navigation.hidden_button', {}, true)}
            </LinkButton>
          ))}
        </div>
        {(active === 'hidden' ? canModerateCommunityNotes() : canViewCommunityNotes()) && (
          <Button
            className="Button Button--icon Button--link CommunityNotesPage-refresh"
            icon="fas fa-rotate-right"
            aria-label={app.translator.trans('ffans-community-notes.forum.navigation.refresh_a11y_label', {}, true)}
            title={app.translator.trans('ffans-community-notes.forum.navigation.refresh_a11y_label', {}, true)}
            disabled={state.loading}
            onclick={() => state.load()}
          />
        )}
      </nav>
    );
  }
}
