import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import LineAccountSection from './components/LineAccountSection';

app.initializers.add('tapao-line-notification', () => {
  // Inject LINE connect/disconnect section into the Account area of user Settings
  extend(SettingsPage.prototype, 'accountItems', function (items) {
    items.add(
      'tapao-line-notification',
      <LineAccountSection />,
      -10 // Priority: render below the default account items
    );
  });
});
