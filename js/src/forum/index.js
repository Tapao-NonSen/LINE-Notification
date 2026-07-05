import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';

import LineAccountSection from './components/LineAccountSection';

app.initializers.add('tapao-line-notification', () => {
  // Inject LINE connect/disconnect section into the Account area of user Settings
  extend('flarum/forum/components/SettingsPage', 'accountItems', function (items) {
    items.add(
      'tapao-line-notification',
      <LineAccountSection />,
      -10 // Priority: render below the default account items
    );
  });

  // Inject LINE column into the Notification Preferences Grid only if connected
  extend('flarum/forum/components/NotificationGrid', 'notificationMethods', function (items) {
    if (app.session.user && app.session.user.attribute('lineUserId')) {
      items.add('line', {
        name: 'line',
        icon: 'fab fa-line',
        label: app.translator.trans('tapao-line-notification.lib.notification.line_driver_label'),
      });
    }
  });
});
