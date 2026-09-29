import Model from 'flarum/common/Model';
import Post from 'flarum/common/models/Post';
import User from 'flarum/common/models/User';

export type NoteStatus = 'needs_more_ratings' | 'helpful' | 'not_helpful';
export type RatingValue = 'helpful' | 'somewhat_helpful' | 'not_helpful';
export interface MyRating {
  value: RatingValue;
  reasons: string[];
}

export default class CommunityNote extends Model {
  reason = Model.attribute<string>('reason');
  content = Model.attribute<string>('content');
  status = Model.attribute<NoteStatus>('status');
  ratingCount = Model.attribute<number>('ratingCount');
  score = Model.attribute<number | null | undefined>('score');
  sources = Model.attribute<string[]>('sources');
  isMine = Model.attribute<boolean>('isMine');
  isHidden = Model.attribute<boolean>('isHidden');
  hiddenReason = Model.attribute<string | null>('hiddenReason');
  myRating = Model.attribute<MyRating | null | undefined>('myRating');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  post = Model.hasOne<Post>('post');
  user = Model.hasOne<User | null>('user');
}
