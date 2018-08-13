<div class="reveal" id="help-modal" data-reveal>


    <!--    Contact Status-->
    <div class="help-section" id="overall-status-help-text" style="display: none">
        <h3 class="lead">Contact Status</h3>
        <p>This is where you set the current status of the contact.</p>
        <ul>
            <li>Unassigned - The contact is new in the system and/or has not been assigned to someone.</li>
            <li>Assigned - The contact has been assigned to someone, but has not yet been accepted by that person.</li>
            <li>Active - The contact is progressing and/or continually being updated. </li>
            <li>Paused - This contact is currently on hold (i.e. on vacation or not responding). </li>
            <li>Closed - This contact has made it known that they no longer want to continue or you have decided not to continue with him/her.</li>
            <li>Unassignable - There is not enough information to move forward with the contact at this time.</li>
        </ul>
    </div>


    <div class="help-section" id="quick-action-help-text" style="display: none">
        <h3 class="lead">Quick action buttons</h3>
        <p>These quick action buttons are here to aid you in updating the contact record.
        They track how many times each one has been used.</p>
        <p>They also update the "Seeker Path" below. For example,
            If you click the "No Answer" button 4 times, a number will be added to "No Answer" meaning that you have
            attempted to call the contact 4 times, but they didn't answer.
            This will also change the "Seeker Path" below to "Contact Attempted".
        </p>
    </div>
    <div class="help-section" id="contact-progress-help-text" style="display: none">
        <h3 class="lead">Contact Progress</h3>
        <p>Here you can track the progress of a contact's faith journey.</p>
    </div>
    <div class="help-section" id="seeker-path-help-text" style="display: none">
        <h3 class="lead">Seeker Path</h3>
        <p>This is where you set the status of your progression with the contact.</p>
    </div>
    <div class="help-section" id="faith-milestones-help-text" style="display: none">
        <h3 class="lead">Faith Milestones</h3>
        <p>This is where you set which milestones the contact has reached in their faith journey.</p>
    </div>

    <!--  Health Metrics  -->
    <div class="help-section" id="health-metrics-help-text" style="display: none">
        <h3 class="lead">Health Metrics</h3>
        <p> Here you can track the progress of a group/church.</p>
        <p>If the group has committed to be a church, click the "Covenant" button to make the dotted line circle solid.</p>
        <p>If the group/church regularly practices any of the following elements then click
            each element to add them inside the circle.</p>
    </div>

    <!--  Group type  -->
    <div class="help-section" id="group-type-help-text" style="display: none">
        <h3 class="lead">Group type</h3>
        <p>Here you can select whether the group is a pre-group, group or church.</p>
        <p>We define a pre-group as having x people. We define a group as having x people.</p>
        <p>We define a church as having 3 or more believers.</p>
    </div>

    <!--  Group Status  -->
    <div class="help-section" id="group-status-help-text" style="display: none">
        <h3 class="lead">Group Status</h3>
        <p>This is where you set the current status of the group. </p>
        <ul>
            <li>
                Active - the group is actively meeting and is continually being updated.
            </li>
            <li>
                Inactive - The group is no longer meeting at this time.
            </li>
        </ul>
    </div>

    <!--  Group Parents and Children  -->
    <div class="help-section" id="group-connections-help-text" style="display: none">
        <h3 class="lead">Group Connections. Parent and Child Groups</h3>
        <p>If this group has multiplied from another group, you can add that group here (Parent Group).</p>
        <p>If this group has multiplied into another group, you can add that here (Child Groups).</p>
    </div>

    <!--    -->
    <div class="help-section" id="-help-text" style="display: none">
        <h3 class="lead"></h3>
        <p></p>
    </div>

    <!--    -->
    <div class="help-section" id="-help-text" style="display: none">
        <h3 class="lead"></h3>
        <p></p>
    </div>




    <div class="grid-x">
        <button class="button" data-close aria-label="Close reveal" type="button">
            <?php esc_html_e( 'Close', 'disciple_tools' )?>
        </button>
        <button class="close-button" data-close aria-label="Close modal" type="button">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
</div>
