import { store } from './store';
import { track } from './track';

/**
 * Who is signed in, for the shared header and tab bar on pages without the community component (the
 * quiz). Reads the community's stored sign-in; "Me" is then a plain link to /me, which offers sign-in.
 */
export default function siteNav() {
    return {
        member: store.get('bd.member', null),
        unread: 0,

        get signedIn() {
            return !!(this.member?.token && this.member.account);
        },

        get myName() {
            return this.member?.name ?? '';
        },

        init() {
            if (!this.signedIn) return;
            fetch('/api/notifications/unread', { headers: { Accept: 'application/json', 'X-Member-Token': this.member.token } })
                .then((res) => (res.ok ? res.json() : null))
                .then((data) => data && (this.unread = data.count))
                .catch(() => {});
        },

        meClick() {},

        // From the quiz into the community through the site navigation (the dashboard's quiz → community panel).
        navClick(to) {
            if (to === 'feed' || to === 'ask') track('community_clicked', { meta: { from: 'nav', to } });
        },
    };
}
