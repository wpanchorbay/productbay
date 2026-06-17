import React from 'react';
import { cn } from '@/utils/cn';
import { __ } from '@wordpress/i18n';
import { TableSettings } from '@/types';
import { Slot } from '@wordpress/components';
import { Toggle } from '@/components/ui/Toggle';
import { Select } from '@/components/ui/Select';
import { ProBadge } from '@/components/ui/ProBadge';
import SectionHeading from '@/components/Table/SectionHeading';
import { SettingsOption } from '@/components/Table/SettingsOption';

/* =============================================================================
 * OptionsPanel Component
 * =============================================================================
 * Reusable panel for configuring functional table settings.
 * ============================================================================= */

/**
 * SettingsSection Component (Internal Helper)
 */
interface SettingsSectionProps {
	title: string;
	description?: string;
	children: React.ReactNode;
}

const SettingsSection = ({ title, description, children }: SettingsSectionProps) => (
	<section className="space-y-6">
		<SectionHeading title={title} description={description} />
		<div className="">{children}</div>
	</section>
);

export interface OptionsPanelProps {
	settings: TableSettings;
	setFeatures: (features: Partial<TableSettings['features']>) => void;
	setPagination: (pagination: Partial<TableSettings['pagination']>) => void;
	setCart: (cart: Partial<TableSettings['cart']>) => void;
	setFilters: (filters: Partial<TableSettings['filters']>) => void;
	className?: string;
}

export const OptionsPanel = ({
	settings,
	setFeatures,
	setPagination,
	setCart,
	setFilters,
	className,
}: OptionsPanelProps) => {
	const isProActive = !!(window as any).productBaySettings?.proVersion;

	return (
		<div className={cn('w-full p-4 space-y-8', className)}>
			{/* Table Controls - User-facing features */}
			<SettingsSection
				title={__('Table Controls', 'productbay')}
				description={__('Configure table functionality and user controls', 'productbay')}
			>
				{/* Enable Search Bar */}
				<SettingsOption
					title={__('Enable Search Bar', 'productbay')}
					description={__('Allow users to search through products', 'productbay')}
				>
					<Toggle
						checked={settings.features.search}
						onChange={(e) => setFeatures({ search: e.target.checked })}
					/>
				</SettingsOption>

				{/* Enable Pagination */}
				<SettingsOption
					title={__('Enable Pagination', 'productbay')}
					description={__('Break large product lists into pages', 'productbay')}
				>
					<Toggle
						checked={settings.features.pagination}
						onChange={(e) => setFeatures({ pagination: e.target.checked })}
					/>
				</SettingsOption>

				{/* Products Per Page */}
				<SettingsOption
					title={__('Products Per Page', 'productbay')}
					description={__('Number of products to display per page', 'productbay')}
				>
					<input
						type="number"
						min="1"
						max="500"
						value={settings.pagination.limit}
						onChange={(e) =>
							setPagination({
								limit: parseInt(e.target.value) || 10,
							})
						}
						className="w-24 h-9 px-3 py-2 text-center border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
					/>
				</SettingsOption>

				{/* Pagination Style */}
				<SettingsOption
					title={__('Pagination Style', 'productbay')}
					description={__('Choose how additional products are loaded', 'productbay')}
				>
					<div className="flex items-center gap-2">
						<Select
							value={settings.pagination.mode || 'standard'}
							onChange={(val) =>
								setPagination({
									mode: val as 'standard' | 'load_more' | 'infinite',
								})
							}
							size="sm"
							className="w-48"
							options={[
								{
									label: __('Standard (Numbers)', 'productbay'),
									value: 'standard',
								},
								{
									label: __('Load More Button', 'productbay') + (isProActive ? '' : ' (PRO)'),
									value: 'load_more',
									disabled: !isProActive,
								},
								{
									label: __('Infinite Scroll', 'productbay') + (isProActive ? '' : ' (PRO)'),
									value: 'infinite',
									disabled: !isProActive,
								},
							]}
						/>
						{!isProActive && <ProBadge />}
					</div>
				</SettingsOption>

				{/* Enable Image Lightbox */}
				<SettingsOption
					title={__('Enable Image Lightbox', 'productbay')}
					description={__(
						'Show full-size product images in a popup when clicked',
						'productbay'
					)}
				>
					<Toggle
						checked={settings.features.lightbox ?? true}
						onChange={(e) => setFeatures({ lightbox: e.target.checked })}
					/>
				</SettingsOption>
			</SettingsSection>

			{/* Taxonomy & Type Filters */}
			<SettingsSection
				title={__('Taxonomy & Type Filters', 'productbay')}
				description={__('Configure frontend dropdown filters', 'productbay')}
			>
				<SettingsOption
					title={__('Enable Categories Filter', 'productbay')}
					description={__('Allow users to filter products by category', 'productbay')}
				>
					<Toggle
						checked={settings.filters?.showCategory ?? true}
						onChange={(e) => setFilters({ showCategory: e.target.checked })}
					/>
				</SettingsOption>
				<SettingsOption
					title={__('Enable Product Type Filter', 'productbay')}
					description={__(
						'Allow users to filter by product type (Simple, Variable, etc.)',
						'productbay'
					)}
				>
					<Toggle
						checked={settings.filters?.showType ?? true}
						onChange={(e) => setFilters({ showType: e.target.checked })}
					/>
				</SettingsOption>

				{/* Price Range Filter (Pro Shell - shown only when Pro is not active) */}
				{!isProActive && (
					<SettingsOption
						title={__('Price Range Filter', 'productbay')}
						description={__(
							'Allow users to filter products by a price range slider',
							'productbay'
						)}
					>
						<ProBadge />
					</SettingsOption>
				)}
			</SettingsSection>

			{/* Cart Settings */}
			<SettingsSection
				title={__('Cart Functionality', 'productbay')}
				description={__('Configure Add to Cart behavior', 'productbay')}
			>
				<SettingsOption
					title={__('AJAX Add to Cart', 'productbay')}
					description={__(
						'Add products to cart inline without page reload. When disabled, button links to product page instead.',
						'productbay'
					)}
				>
					<Toggle
						checked={settings.cart.enable}
						onChange={(e) => setCart({ enable: e.target.checked })}
					/>
				</SettingsOption>

				<SettingsOption
					title={__('Show Quantity Selector', 'productbay')}
					description={__(
						'Display quantity input next to add-to-cart button',
						'productbay'
					)}
				>
					<Toggle
						checked={settings.cart.showQuantity}
						onChange={(e) => setCart({ showQuantity: e.target.checked })}
					/>
				</SettingsOption>

				<SettingsOption
					title={__('Show Clear All Button', 'productbay')}
					description={__(
						'Display a button to instantly clear all selected products',
						'productbay'
					)}
				>
					<Toggle
						checked={settings.features.clearAllButton}
						onChange={(e) =>
							setFeatures({
								clearAllButton: e.target.checked,
							})
						}
					/>
				</SettingsOption>

				<SettingsOption
					title={__('Selected Items View Panel', 'productbay')}
					description={__(
						'Show a floating panel displaying all selected items with individual quantities',
						'productbay'
					)}
				>
					<Toggle
						checked={settings.features.selectedItemsPanel?.enabled ?? true}
						onChange={(e) =>
							setFeatures({
								selectedItemsPanel: {
									...settings.features.selectedItemsPanel,
									enabled: e.target.checked,
								},
							})
						}
					/>
				</SettingsOption>

				{!isProActive ? (
					<>
						<SettingsOption
							title={__('Custom \"Add to Cart\" Button Text', 'productbay')}
							description={__('Override the global text for this specific table', 'productbay')}
						>
							<ProBadge />
						</SettingsOption>
						<SettingsOption
							title={__('Custom \"Select Options\" Button Text', 'productbay')}
							description={__('Text for \"Select Options\" / \"View Products\" buttons that open popups or nested rows', 'productbay')}
						>
							<ProBadge />
						</SettingsOption>
					</>
				) : (
					<Slot name="productbay-pro-custom-add-to-cart-text-option" />
				)}

				{/* Variable & Grouped Products - Display modes */}
				{(() => {
					const variableMode = settings.features.variableProductMode || settings.features.variationsMode || 'inline';
					const groupedMode = settings.features.groupedProductMode || (settings.features.variationsMode !== 'inline' ? settings.features.variationsMode : 'popup') || 'popup';

					const variableModeOptions = [
						{ label: __('Inline Dropdown', 'productbay'), value: 'inline' },
						{ label: __('Popup Modal (PRO)', 'productbay'), value: 'popup', disabled: !isProActive },
						{ label: __('Nested Rows (PRO)', 'productbay'), value: 'nested', disabled: !isProActive },
						{ label: __('Separate Rows (PRO)', 'productbay'), value: 'separate', disabled: !isProActive },
					];

					const groupedModeOptions = [
						{ label: __('Inline Dropdown', 'productbay'), value: 'inline' },
						{ label: __('Popup Modal (PRO)', 'productbay'), value: 'popup', disabled: !isProActive },
						{ label: __('Nested Rows (PRO)', 'productbay'), value: 'nested', disabled: !isProActive },
						{ label: __('Separate Rows (PRO)', 'productbay'), value: 'separate', disabled: !isProActive },
					];

					// Check if either uses nested
					const hasNestedMode = variableMode === 'nested' || groupedMode === 'nested';

					return (
						<>
							<div className="pt-4 border-t border-gray-100 mt-6">
								<h4 className="text-sm font-medium text-gray-900 mb-1">{__('Variable & Grouped Products', 'productbay')}</h4>
								<p className="text-xs text-gray-500 mb-4">{__('Configure how complex products are displayed. Advanced modes require PRO.', 'productbay')}</p>

								<SettingsOption
									title={__('Grouped Products', 'productbay')}
									description={__('Products containing multiple child simple products', 'productbay')}
								>
									<div className="flex items-center gap-2 ml-2">
										<Select
											value={groupedMode}
											onChange={(value: string) => setFeatures({ groupedProductMode: value as 'inline' | 'popup' | 'nested' | 'separate' })}
											options={groupedModeOptions}
											className="w-60"
										/>
										{!isProActive && <ProBadge />}
									</div>
								</SettingsOption>

								<SettingsOption
									title={__('Variable Products', 'productbay')}
									description={__('Products with attribute variations. Inline dropdown supported natively.', 'productbay')}
								>
									<div className="flex items-center gap-2 ml-2">
										<Select
											value={variableMode}
											onChange={(value: string) => setFeatures({
												variableProductMode: value as 'inline' | 'popup' | 'nested' | 'separate',
												variationsMode: value as 'inline' | 'popup' | 'nested' | 'separate', // Sync legacy
											})}
											options={variableModeOptions}
											className="w-60"
										/>
										{!isProActive && <ProBadge />}
									</div>
								</SettingsOption>

								{hasNestedMode && isProActive && (
									<SettingsOption
										title={__('Expand Nested Rows', 'productbay')}
										description={__('Show nested rows expanded by default instead of collapsed', 'productbay')}
									>
										<Toggle
											checked={settings.features.nestedDefaultExpanded ?? false}
											onChange={(e) => setFeatures({ nestedDefaultExpanded: e.target.checked })}
										/>
									</SettingsOption>
								)}

								<SettingsOption
									title={__('Show Options Count', 'productbay')}
									description={__('Display "X options available" subtitle below product name', 'productbay')}
								>
									<Toggle
										checked={settings.features.showChildCount ?? true}
										onChange={(e) => setFeatures({ showChildCount: e.target.checked })}
									/>
								</SettingsOption>
							</div>
						</>
					);
				})()}

				<SettingsOption
					title={__('Variation Badges', 'productbay')}
					description={__(
						'Show badges indicating which variations were added to cart',
						'productbay'
					)}
				>
					<Toggle
						checked={settings.features.variationBadges}
						onChange={(e) =>
							setFeatures({
								variationBadges: e.target.checked,
							})
						}
					/>
				</SettingsOption>

			</SettingsSection>

			{/* Pro features inject here */}
			<Slot name="productbay-pro-options" />
		</div>
	);
};
