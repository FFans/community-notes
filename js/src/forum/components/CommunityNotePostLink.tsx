import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import type Post from 'flarum/common/models/Post';
import app from 'flarum/forum/app';

export default class CommunityNotePostLink extends Component<{ post: Post; heading?: boolean }> {
  view() {
    const post = this.attrs.post;
    const discussion = post.discussion();

    if (!discussion) return null;

    const number = post.number();
    const link = (
      <Link
        href={app.route.post(post)}
        aria-label={
          number > 0
            ? app.translator.trans(
                'ffans-community-notes.forum.post_link.with_number_a11y_label',
                { title: discussion.title(), number },
                true
              )
            : app.translator.trans(
                'ffans-community-notes.forum.post_link.a11y_label',
                { title: discussion.title() },
                true
              )
        }
      >
        {discussion.title()}
      </Link>
    );

    return (
      <div className="CommunityNotePostLink">
        {this.attrs.heading ? (
          <h3>
            {link}
            {number > 0 && (
              <span className="CommunityNotePostLink-floor" aria-hidden="true">
                {app.translator.trans('ffans-community-notes.forum.post_link.number', { number })}
              </span>
            )}
          </h3>
        ) : (
          link
        )}
      </div>
    );
  }
}
