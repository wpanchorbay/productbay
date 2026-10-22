import { memo, useState } from 'react';
import { DatabaseZapIcon } from 'lucide-react';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { __ } from '@wordpress/i18n';
import { useToast } from '@/context/ToastContext';
import { Skeleton } from '@/components/ui/Skeleton';
import { apiFetch } from '@/utils/api';

interface ClearCacheOptionsProps {
	loading: boolean;
}

/**
 * ClearCacheOptions Component
 *
 * Settings section for clearing ProductBay's cached filter-dropdown choices,
 * so every table rebuilds the category and product-type options it offers on
 * its next view instead of waiting for the cache to expire on its own.
 *
 * @since 1.3.5
 */
const ClearCacheOptions = memo(({ loading }: ClearCacheOptionsProps) => {
	const [showConfirmModal, setShowConfirmModal] = useState(false);
	const [clearing, setClearing] = useState(false);
	const { toast } = useToast();

	const handleClearCache = async () => {
		setClearing(true);
		try {
			await apiFetch('system/clear-cache', { method: 'POST' });
			toast({
				title: __('Cache Cleared', 'productbay'),
				description: __(
					'Tables will rebuild their filter choices on the next view.',
					'productbay'
				),
				type: 'success',
			});
			setShowConfirmModal(false);
		} catch (error) {
			toast({
				title: __('Failed to Clear Cache', 'productbay'),
				description: __(
					'Could not clear the cache. Please try again.',
					'productbay'
				),
				type: 'error',
			});
		} finally {
			setClearing(false);
		}
	};

	// Loading state
	if (loading) {
		return (
			<div className="p-6">
				<div className="h-7 w-40 bg-gray-200 rounded animate-pulse mb-6" />
				<div className="flex items-center justify-between p-4 border border-gray-100 rounded-lg">
					<div className="space-y-2">
						<div className="h-5 w-48 bg-gray-200 rounded animate-pulse" />
						<div className="h-4 w-64 bg-gray-200 rounded animate-pulse" />
					</div>
					<Skeleton className="h-10 w-32 rounded" />
				</div>
			</div>
		);
	}

	return (
		<div className="p-6">
			<h3 className="text-lg font-semibold text-gray-800 mt-0 mb-4">
				{__('Performance', 'productbay')}
			</h3>
			<div className="flex items-center justify-between p-4 border border-gray-200 bg-gray-50 rounded-lg">
				<div>
					<span className="text-sm font-bold text-gray-800">
						{__('Clear cached filter options', 'productbay')}
					</span>
					<p className="text-sm text-gray-600 mt-1">
						{__(
							'Tables remember which categories and product types to offer in their filter dropdowns. That list is rebuilt automatically whenever your products change — use this to rebuild it right now. Products, prices and stock are always read live and are never cached.',
							'productbay'
						)}
					</p>
				</div>
				<Button
					variant="outline"
					onClick={() => setShowConfirmModal(true)}
					disabled={clearing}
					title={__('Rebuild cached filter options', 'productbay')}
					className="flex-shrink-0 cursor-pointer"
				>
					{__('Clear cache', 'productbay')}
					<DatabaseZapIcon className="w-4 h-4 ml-2" />
				</Button>
			</div>

			{/* Clear cache confirmation modal */}
			<Modal
				isOpen={showConfirmModal}
				onClose={() => setShowConfirmModal(false)}
				title={__('Clear Cached Filter Options?', 'productbay')}
				maxWidth="sm"
				closeOnBackdropClick={true}
				primaryButton={{
					text: clearing
						? __('Clearing...', 'productbay')
						: __('Yes, Clear Cache', 'productbay'),
					onClick: handleClearCache,
					variant: 'primary',
					icon: <DatabaseZapIcon className="w-4 h-4" />,
					disabled: clearing,
				}}
				secondaryButton={{
					text: __('Cancel', 'productbay'),
					onClick: () => setShowConfirmModal(false),
					variant: 'secondary',
					disabled: clearing,
				}}
			>
				<p className="text-gray-600 m-0">
					{__(
						'Every table will rebuild the choices in its filter dropdowns the next time it is viewed. Nothing is deleted, and your tables are unaffected.',
						'productbay'
					)}
				</p>
			</Modal>
		</div>
	);
});

export default ClearCacheOptions;
