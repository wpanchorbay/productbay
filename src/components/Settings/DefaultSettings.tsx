import { DefaultColumnsConfig } from '@/components/Table/sections/DefaultColumnsConfig';
import { BulkSelectConfig } from '@/components/Table/sections/BulkSelectConfig';
import { DisplayPanel } from '@/components/Table/panels/DisplayPanel';
import { OptionsPanel } from '@/components/Table/panels/OptionsPanel';
import { SourcePanel } from '@/components/Table/panels/SourcePanel';
import { useTableStore } from '@/store/tableStore';
import { __ } from '@wordpress/i18n';
import React, { useEffect, useRef } from 'react';

interface DefaultSettingsProps {
	source: any;
	setSourceType: (type: any) => void;
	columns: any;
	setColumns: (cols: any) => void;
	tableSettings: any;
	setFeatures: (v: any) => void;
	style: any;
	setHeaderStyle: (v: any) => void;
	setBodyStyle: (v: any) => void;
	setButtonStyle: (v: any) => void;
	setLayoutStyle: (v: any) => void;
	setTypographyStyle: (v: any) => void;
	setHoverStyle: (v: any) => void;
	setResponsiveStyle: (v: any) => void;
	setPagination: (v: any) => void;
	setCart: (v: any) => void;
	setFilters: (v: any) => void;
}

/**
 * DefaultSettings Component
 *
 * Handles the "Default Configuration" tab in settings.
 * Allows setting global defaults for new tables (Source, Style, Functionality).
 *
 * Includes a bidirectional sync bridge between the Settings page's store and the
 * tableStore so that Pro slot fills (PriceFilterSlot, VariationsSlot) — which
 * read/write window.productbay.useTableStore — can operate on the correct data
 * when rendered inside the Settings page's OptionsPanel <Slot>.
 */
const DefaultSettings: React.FC<DefaultSettingsProps> = ({
	source,
	setSourceType,
	columns,
	setColumns,
	tableSettings,
	setFeatures,
	style,
	setHeaderStyle,
	setBodyStyle,
	setButtonStyle,
	setLayoutStyle,
	setTypographyStyle,
	setHoverStyle,
	setResponsiveStyle,
	setPagination,
	setCart,
	setFilters,
}) => {
	/**
	 * Ref to prevent circular sync loops.
	 * When the settings page pushes data INTO tableStore, we set this flag
	 * so the tableStore subscriber doesn't echo the change back.
	 */
	const syncingRef = useRef(false);

	/**
	 * Push settings page defaults into tableStore whenever tableSettings changes.
	 * This ensures Pro fills (which call useTableStore()) see the correct values.
	 */
	useEffect(() => {
		syncingRef.current = true;
		useTableStore.setState({ settings: tableSettings });
		// Use microtask so the synchronous Zustand subscriber fires with flag=true,
		// then we re-enable backward sync for future Pro fill interactions.
		Promise.resolve().then(() => {
			syncingRef.current = false;
		});
	}, [tableSettings]);

	/**
	 * Subscribe to tableStore.settings.features changes.
	 * When a Pro fill updates features via tableStore.setFeatures(),
	 * forward those changes to the settings page's setFeatures().
	 */
	useEffect(() => {
		let prevFeatures = useTableStore.getState().settings.features;

		const unsub = useTableStore.subscribe((state) => {
			// Skip if this update was triggered by the settings page sync above
			if (syncingRef.current) {
				prevFeatures = state.settings.features;
				return;
			}

			const currentFeatures = state.settings.features;
			if (currentFeatures !== prevFeatures) {
				// Compute the diff: only forward keys that actually changed
				const diff: Record<string, any> = {};
				for (const key of Object.keys(currentFeatures as any)) {
					if ((currentFeatures as any)[key] !== (prevFeatures as any)?.[key]) {
						diff[key] = (currentFeatures as any)[key];
					}
				}
				prevFeatures = currentFeatures;
				if (Object.keys(diff).length > 0) {
					setFeatures(diff);
				}
			}
		});

		return unsub;
	}, [setFeatures]);

	return (
		<div className="space-y-10 p-6">
			<div className="max-w-4xl space-y-10">
				<div>
					<h2 className="text-lg font-bold text-gray-900 mb-2">
						{__('Default Source', 'productbay')}
					</h2>
					<p className="text-gray-500 mb-6">
						{__('Configure the default data source settings for new tables.', 'productbay')}
					</p>

					<SourcePanel
						source={source}
						setSourceType={setSourceType}
						className="border-none"
					/>
				</div>

				<hr className="border-b-2 border-gray-200" />

				<div className="flex flex-col gap-8">
					<div>
						<h2 className="text-lg font-bold text-gray-900 mb-2">
							{__('Default Columns', 'productbay')}
						</h2>
						<p className="text-gray-500 mb-6">
							{__('Configure the default columns for new tables.', 'productbay')}
						</p>
						<DefaultColumnsConfig columns={columns} onChange={setColumns} />
					</div>

					{/* Bulk Select Configuration */}
					<BulkSelectConfig
						value={tableSettings.features.bulkSelect}
						onChange={(config) => setFeatures({ bulkSelect: config })}
					/>
				</div>

				<hr className="border-b-2 border-gray-200" />

				<div>
					<h2 className="text-lg font-bold text-gray-900 mb-2">
						{__('Default Styling', 'productbay')}
					</h2>
					<p className="text-gray-500 mb-6">
						{__('Set the default look and feel for your tables.', 'productbay')}
					</p>
					<DisplayPanel
						style={style}
						setHeaderStyle={setHeaderStyle}
						setBodyStyle={setBodyStyle}
						setButtonStyle={setButtonStyle}
						setLayoutStyle={setLayoutStyle}
						setTypographyStyle={setTypographyStyle}
						setHoverStyle={setHoverStyle}
						setResponsiveStyle={setResponsiveStyle}
						className="border-none"
					/>
				</div>

				<hr className="border-b-2 border-gray-200" />

				<div>
					<h2 className="text-lg font-bold text-gray-900 mb-2">
						{__('Default Functionality', 'productbay')}
					</h2>
					<p className="text-gray-500 mb-6">
						{__(
							'Configure default features like sorting, pagination, and filters.',
							'productbay'
						)}
					</p>
					<OptionsPanel
						settings={tableSettings}
						setFeatures={setFeatures}
						setPagination={setPagination}
						setCart={setCart}
						setFilters={setFilters}
						className="border-none"
					/>
				</div>
			</div>
		</div>
	);
};

export default DefaultSettings;
