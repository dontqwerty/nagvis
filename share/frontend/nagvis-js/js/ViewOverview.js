/*****************************************************************************
 *
 * ViewOverview.js - All NagVis overview related top level code
 *
 * Copyright (c) 2004-2015 NagVis Project (Contact: info@nagvis.org)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/

var ViewOverview = View.extend({
    rendered_maps  : 0,
    processed_maps : 0,

    constructor: function() {
        this.base(null);
    },

    init: function() {
        this.renderPageBasics();
        this.render();
        this.loadMaps();
        this.loadRotations(();
    },

    update: function() {
        var to_update = this.getObjectsToUpdate();
        this.base({
            mod  : 'Overview',
            data : to_update[0]
        });
    },

    /**
     * END OF PUBLIC METHODS
     */
    
    render: function() {
        this.dom_obj = document.getElementById('overview');

        // Render maps and the rotations when enabled
        var types = [
            [ oPageProperties.showmaps,      'overviewMaps',      oPageProperties.lang_mapIndex ],
            [ oPageProperties.showrotations, 'overviewRotations', oPageProperties.lang_rotationPools ]
        ];
        for (var i = 0; i < types.length; i++) {
            if (types[i][0] === 1) {
                var h2 = document.createElement('h2');
                h2.innerHTML = types[i][2];
                this.dom_obj.appendChild(h2);

                var container = document.createElement('div');
                container.setAttribute('id', types[i][1]);
                container.className = 'infobox';
                this.dom_obj.appendChild(container);
            }
        }
    },

    // Adds a single map to the overview map list
    addMap: function(map_conf, map_name) {
        this.processed_maps += 1;
    
        // Exit this function on invalid call
        if(map_conf === null || map_conf.length != 1)  {
            eventlog("worker", "warning", "addOverviewMap: Invalid call - maybe broken ajax response ("+map_name+")");
            if (this.processed_maps == g_map_names.length)
                finishOverviewMaps();
            return false;
        }
    
        this.rendered_maps += 1; // also count errors
    
        var container = document.getElementById('overviewMaps');
    
        // Find the map placeholder div (replace it to keep sorting)
        var mapdiv = null;
        var child = null;
        for (var i = 0; i < container.childNodes.length; i++) {
            child = container.childNodes[i];
            if (child.id == map_name) {
                mapdiv = child;
                break;
            }
        }
    
        // render the map object
        var obj = new NagVisMap(map_conf[0]);
        // Save object to map objects array
        this.objects[obj.conf.object_id] = obj;
        obj.update();
        obj.render();
        container.replaceChild(obj.dom_obj, mapdiv);
    
        // Finalize rendering after last map...
        if (this.processed_maps == g_map_names.length)
            this.finishMaps();
    },
    
    finishMaps: function() {
        // Hide the "Loading..." message. This is not the best place since rotations 
        // might not have been loaded now but in most cases this is the longest running request
        hideStatusMessage();
    },
    
    // Does initial parsing of rotations on the overview page
    addRotations: function(rotations) {
        if (oPageProperties.showrotations === 1 && rotations.length > 0) {
            for (var i = 0, len = rotations.length; i < len; i++) {
                new NagVisRotation(rotations[i]).parseOverview();
            }
        } else {
            // Hide the rotations container
            var container = document.getElementById('overviewRotations');
            if (container) {
                container.style.display = 'none';
            }
        }
    },
    
    // Fetches all maps to be shown on the overview page
    loadMaps: function() {
        var map_container = document.getElementById('overviewMaps');
    
        if (oPageProperties.showmaps !== 1 || g_map_names.length == 0) {
            if (map_container)
                map_container.parentNode.style.display = 'none';
            hideStatusMessage();
            return false;
        }
    
        for (var i = 0, len = g_map_names.length; i < len; i++) {
            var mapdiv = document.createElement('div');
            mapdiv.setAttribute('id', g_map_names[i])
            map_container.appendChild(mapdiv);
            getAsyncRequest(oGeneralProperties.path_server+'?mod=Overview&act=getObjectStates'
                            + '&i[]=map-' + escapeUrlValues(g_map_names[i]) + getViewParams(),
                            false, this.addMap, g_map_names[i]);
        }
    },
    
    // Fetches all rotations to be shown on the overview page
    loadRotations: function() {
        getAsyncRequest(oGeneralProperties.path_server+'?mod=Overview&act=getOverviewRotations',
                        false, this.addRotations);
    }
});