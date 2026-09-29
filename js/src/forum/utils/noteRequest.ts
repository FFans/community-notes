// 普通阅读与评价页面只请求实际使用的字段，避免携带全部评价和管理历史。
// Core rc.8 的 Store 将 fields 类型限制为数组；使用标准查询键传递资源字段集。
export const noteRequest = {
  include: 'post,post.user,post.user.groups,post.discussion,post.discussion.user,post.communityNote,user',
  'fields[community-notes]': 'reason,content,status,ratingCount,sources,isMine,isHidden,myRating,post,user',
};

// 隐藏原因只在打开管理弹窗时读取。
export const moderationRequest = {
  ...noteRequest,
  'fields[community-notes]': `${noteRequest['fields[community-notes]']},hiddenReason`,
};
