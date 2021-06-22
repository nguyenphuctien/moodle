// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contain the events the form component can trigger.
 *
 * @module core_form/events
 * @package core_form
 * @copyright 2021 Huong Nguyen <huongnv13@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since 3.10
 */

import {get_string as getString} from 'core/str';

let changesMadeString;
getString('changesmadereallygoaway', 'moodle').then(string => {
    changesMadeString = string;
    return string;
}).catch();

/**
 * Prevent user navigate away when upload progress still running.
 * @param {Event} e The event
 */
const changesMadeCheck = e => {
    if (e) {
        e.returnValue = changesMadeString;
    }
};

/**
 * The object keys are the elementIds (as passed to notifyUploadStarted and notifyUploadCompleted.
 * The corresponding values are the number of uploads currently in-progress for that element.
 */
let numberOfFilesUploading = {};

/**
 * Check if all files are uploaded.
 * @return {Boolean} Are all files uploaded?
 */
const uploadCompleted = () => {
    return Object.values(numberOfFilesUploading).every(val => {
        return val == 0;
    });
};

/**
 * List of the events.
 **/
export const formEventTypes = {
    uploadStarted: 'core_form/uploadStarted',
    uploadCompleted: 'core_form/uploadCompleted',
};

/**
 * Trigger upload start event.
 *
 * @param {String} elementId
 * @returns {CustomEvent<unknown>}
 */
export const triggerUploadStarted = elementId => {
    numberOfFilesUploading[elementId] = numberOfFilesUploading[elementId] ? numberOfFilesUploading[elementId] + 1 : 1;

    // Add an additional check for changes made.
    window.addEventListener('beforeunload', changesMadeCheck);
    const customEvent = new CustomEvent(formEventTypes.uploadStarted, {
        bubbles: true,
        cancellable: false
    });
    const element = document.getElementById(elementId);
    element.dispatchEvent(customEvent);

    return customEvent;
};

/**
 * Trigger upload complete event.
 *
 * @param {String} elementId The element which was uploaded to
 * @param {boolean} all All files from element are uploaded.
 * @returns {CustomEvent | null}
 */
export const triggerUploadCompleted = (elementId, all = false) => {
    // Update number of files uploading from element.
    if (numberOfFilesUploading[elementId] && numberOfFilesUploading[elementId] > 0) {
        numberOfFilesUploading[elementId] = all ? 0 : numberOfFilesUploading[elementId] - 1;
    }

    if (uploadCompleted()) {
        // Remove the additional check for changes made.
        window.removeEventListener('beforeunload', changesMadeCheck);
        const customEvent = new CustomEvent(formEventTypes.uploadCompleted, {
            bubbles: true,
            cancellable: false
        });
        const element = document.getElementById(elementId);
        element.dispatchEvent(customEvent);

        return customEvent;
    } else {
        return null;
    }
};
