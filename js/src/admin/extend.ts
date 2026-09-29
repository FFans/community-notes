import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

import commonExtend from '../common/extend';

export default [
  ...commonExtend,

  new Extend.Admin()
    .setting(() => ({
      setting: 'ffans-community-notes.min_ratings',
      type: 'number',
      label: app.translator.trans('ffans-community-notes.admin.settings.min_ratings_label', {}, true),
      help: app.translator.trans('ffans-community-notes.admin.settings.min_ratings_help', {}, true),
      min: 1,
      max: 100,
      step: 1,
      required: true,
    }))
    .setting(() => ({
      setting: 'ffans-community-notes.helpful_threshold',
      type: 'number',
      label: app.translator.trans('ffans-community-notes.admin.settings.helpful_threshold_label', {}, true),
      help: app.translator.trans('ffans-community-notes.admin.settings.helpful_threshold_help', {}, true),
      min: 0,
      max: 100,
      step: 1,
      required: true,
    }))
    .setting(() => ({
      setting: 'ffans-community-notes.not_helpful_threshold',
      type: 'number',
      label: app.translator.trans('ffans-community-notes.admin.settings.not_helpful_threshold_label', {}, true),
      help: app.translator.trans('ffans-community-notes.admin.settings.not_helpful_threshold_help', {}, true),
      min: 0,
      max: 100,
      step: 1,
      required: true,
    }))
    .permission(
      () => ({
        permission: 'ffans-community-notes.note.create',
        icon: 'fas fa-note-sticky',
        label: app.translator.trans('ffans-community-notes.admin.permissions.create', {}, true),
        allowGuest: false,
      }),
      'start'
    )
    .permission(
      () => ({
        permission: 'ffans-community-notes.note.rate',
        icon: 'fas fa-check-to-slot',
        label: app.translator.trans('ffans-community-notes.admin.permissions.rate', {}, true),
        allowGuest: false,
      }),
      'reply'
    )
    .permission(
      () => ({
        permission: 'ffans-community-notes.note.moderate',
        icon: 'fas fa-shield-halved',
        label: app.translator.trans('ffans-community-notes.admin.permissions.moderate', {}, true),
        allowGuest: false,
      }),
      'moderate'
    ),
];
