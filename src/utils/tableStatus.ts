import { __ } from '@wordpress/i18n';
import { TableStatus } from '@/types';

/**
 * Presentation for each post status a table can carry.
 *
 * The builder only ever sets `publish` or `private`, but
 * `TableRepository::save()` also accepts `draft` and `pending`, so a table
 * created through the REST API, an import, or WP-CLI can arrive with either.
 * Mapping every status explicitly stops those being mislabelled as "Private",
 * which is a meaningfully different thing: private means published but
 * restricted to capable logged-in users, draft means not published at all.
 */
export const TABLE_STATUS_BADGES: Record<
	TableStatus,
	{ label: string; badgeClassName: string; textClassName: string }
> = {
	publish: {
		label: __('Published', 'productbay'),
		badgeClassName: 'bg-green-100 text-green-800',
		textClassName: 'text-green-600',
	},
	private: {
		label: __('Private', 'productbay'),
		badgeClassName: 'bg-yellow-100 text-yellow-800',
		textClassName: 'text-yellow-600',
	},
	draft: {
		label: __('Draft', 'productbay'),
		badgeClassName: 'bg-gray-100 text-gray-700',
		textClassName: 'text-gray-500',
	},
	pending: {
		label: __('Pending Review', 'productbay'),
		badgeClassName: 'bg-blue-100 text-blue-800',
		textClassName: 'text-blue-600',
	},
};

const UNKNOWN_STATUS = {
	label: __('Unknown', 'productbay'),
	badgeClassName: 'bg-gray-100 text-gray-700',
	textClassName: 'text-gray-500',
};

/**
 * Resolve a status to its presentation, falling back gracefully when
 * WordPress reports a status we don't map (another plugin can register one).
 *
 * @param status The table's post status as returned by the REST layer.
 */
export const getTableStatusBadge = (status?: string) =>
	TABLE_STATUS_BADGES[status as TableStatus] ?? UNKNOWN_STATUS;
