/**
 * Editor Integration
 *
 * Registers the revision manager sidebar panel for the block editor.
 */

import './editor.scss';

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';

import RevisionManagerPanel from './components/RevisionManagerPanel';

const RevisionManagerSidebar = () => (
    <Fragment>
        <PluginSidebarMoreMenuItem target="dgw-revision-manager" icon="backup">
            {__('Revision Manager', 'dgwltd-revision-manager')}
        </PluginSidebarMoreMenuItem>
        <PluginSidebar
            name="dgw-revision-manager"
            title={__('Revision Manager', 'dgwltd-revision-manager')}
            icon="backup"
        >
            <RevisionManagerPanel />
        </PluginSidebar>
    </Fragment>
);

registerPlugin('dgw-revision-manager', {
    render: RevisionManagerSidebar,
    icon: 'backup',
});