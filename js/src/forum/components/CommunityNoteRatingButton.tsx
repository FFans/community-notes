import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import app from 'flarum/forum/app';

import CommunityNote from '../models/CommunityNote';
import canRateCommunityNote from '../utils/canRateCommunityNote';
import { ratingSummaries } from '../utils/ratingInput';

export default class CommunityNoteRatingButton extends Component<{ note: CommunityNote; onclick: () => void }> {
  view() {
    const note = this.attrs.note;
    const canRate = canRateCommunityNote(note);
    const rating = canRate ? note.myRating() : null;

    return (
      <Button
        className="Button Button--small"
        onclick={this.attrs.onclick}
        aria-label={
          rating
            ? app.translator.trans(
                'ffans-community-notes.forum.rating.edit_button_a11y_label',
                { rating: app.translator.trans(ratingSummaries[rating.value], {}, true) },
                true
              )
            : app.translator.trans('ffans-community-notes.forum.rating.button_a11y_label', {}, true)
        }
        icon={
          rating?.value === 'helpful'
            ? 'far fa-thumbs-up'
            : rating?.value === 'not_helpful'
              ? 'far fa-thumbs-down'
              : rating?.value === 'somewhat_helpful'
                ? 'far fa-hand-paper'
                : null
        }
      >
        {rating
          ? app.translator.trans(ratingSummaries[rating.value])
          : app.translator.trans('ffans-community-notes.forum.rating.button', {}, true)}
        <Icon name="fas fa-angle-right"></Icon>
      </Button>
    );
  }
}
