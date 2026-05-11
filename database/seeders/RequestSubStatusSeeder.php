<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RequestSubStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $status = [
            [
                'name' => 'In Progress',
                'request_status_id' => 1,
                'description' => 'The requester is still filling out the form but has not yet submitted the request.',
            ],
            [
                'name' => 'Saved For Later',
                'request_status_id' => 1,
                'description' => 'The requester has saved the form to complete and submit at a later time.',
            ],
            [
                'name' => 'Awaiting Review',
                'request_status_id' => 2,
                'description' => 'The request has been submitted and is waiting to be assigned for review.',
            ],
            [
                'name' => 'Submitted with Errors',
                'request_status_id' => 2,
                'description' => 'The request has been submitted, but there are minor errors or missing details that need correction by the requester.',
            ],
            [
                'name' => 'Under Initial Review',
                'request_status_id' => 3,
                'description' => 'The request is being reviewed by the assigned team for eligibility and completeness.',
            ],
            [
                'name' => 'Waiting for Additional Information',
                'request_status_id' => 3,
                'description' => 'The reviewer has requested further information from the requester or beneficiary.',
            ],
            [
                'name' => 'Eligibility Check',
                'request_status_id' => 3,
                'description' => 'The request is undergoing an eligibility review to determine if it qualifies for assistance.',
            ],
            [
                'name' => 'Verification in Progress',
                'request_status_id' => 4,
                'description' => 'The beneficiary’s details are being verified to ensure they meet the eligibility criteria.',
            ],
            [
                'name' => 'Pending Documentation',
                'request_status_id' => 4,
                'description' => 'The beneficiary needs to submit additional documentation or identification to complete the verification.',
            ],
            [
                'name' => 'Verified',
                'request_status_id' => 4,
                'description' => 'The beneficiary has been verified as eligible and can receive assistance.',
            ],
            [
                'name' => 'Verification Failed',
                'request_status_id' => 4,
                'description' => 'The verification failed due to missing or invalid information, making the beneficiary ineligible for assistance.',
            ],
            [
                'name' => 'Beneficiary Not Reachable',
                'request_status_id' => 4,
                'description' => 'Attempts to contact the beneficiary for verification have been unsuccessful.',
            ],
            [
                'name' => 'Assigned to Team',
                'request_status_id' => 5,
                'description' => 'The request has been assigned to a specific team or department for resolution.',
            ],
            [
                'name' => 'Resource Allocation',
                'request_status_id' => 5,
                'description' => 'The team is gathering necessary resources or personnel to fulfill the request.',
            ],
            [
                'name' => 'Action Underway',
                'request_status_id' => 5,
                'description' => 'The team is actively working on resolving the request.',
            ],
            [
                'name' => 'Awaiting External Input',
                'request_status_id' => 6,
                'description' => 'The process is on hold while waiting for information from external parties (e.g., vendors, partners, or government agencies).',
            ],
            [
                'name' => 'Awaiting Internal Approval',
                'request_status_id' => 6,
                'description' => 'The request requires further internal approval or authorization.',
            ],
            [
                'name' => 'Funding Issues',
                'request_status_id' => 6,
                'description' => 'The request is on hold due to budget or funding constraints.',
            ],
            [
                'name' => 'Technical Delay',
                'request_status_id' => 6,
                'description' => 'The request is delayed due to technical issues or equipment unavailability.',
            ],
            [
                'name' => 'Escalated to Management',
                'request_status_id' => 7,
                'description' => 'The request has been escalated to higher-level management for review.',
            ],
            [
                'name' => 'Escalated for Urgency',
                'request_status_id' => 7,
                'description' => 'The request has been escalated due to its urgent nature and requires immediate attention.',
            ],
            [
                'name' => 'Escalated for Special Expertise',
                'request_status_id' => 7,
                'description' => 'The request has been forwarded to a specialized team or expert for resolution.',
            ],
            [
                'name' => 'Approved with Conditions',
                'request_status_id' => 8,
                'description' => 'The request is approved, but specific conditions must be met before the assistance is provided.',
            ],
            [
                'name' => 'Full Approval',
                'request_status_id' => 8,
                'description' => 'The request has been fully approved with no additional requirements.',
            ],
            [
                'name' => 'Pending Beneficiary Info',
                'request_status_id' => 9,
                'description' => 'The system is waiting for the beneficiary to provide necessary information or verification.',
            ],
            [
                'name' => 'Beneficiary Not Reachable',
                'request_status_id' => 9,
                'description' => 'Attempts to contact the beneficiary for confirmation have been unsuccessful.',
            ],
            [
                'name' => 'Successfully Delivered',
                'request_status_id' => 11,
                'description' => 'The assistance has been provided, and the request is marked as resolved.',
            ],
            [
                'name' => 'Partially Completed',
                'request_status_id' => 11,
                'description' => 'The assistance was provided but only partially fulfilled due to limitations.',
            ],
            [
                'name' => 'Delivered with Recommendations',
                'request_status_id' => 11,
                'description' => 'The request is resolved, but the team has provided additional recommendations for future actions or steps.',
            ],
            [
                'name' => 'Eligibility Denied',
                'request_status_id' => 12,
                'description' => 'The request was denied based on eligibility criteria.',
            ],
            [
                'name' => 'Verification Denied',
                'request_status_id' => 12,
                'description' => 'The request was denied due to the beneficiary failing to meet verification requirements.',
            ],
            [
                'name' => 'Resource Unavailability',
                'request_status_id' => 12,
                'description' => 'The request was denied due to the unavailability of resources (e.g., funding, personnel).',
            ],
            [
                'name' => 'Outside Scope',
                'request_status_id' => 12,
                'description' => 'The request falls outside the scope of services offered and has been denied.',
            ],
            [
                'name' => 'Duplicate Request',
                'request_status_id' => 12,
                'description' => 'The request was found to be a duplicate of a previously submitted request and was denied.',
            ],
            [
                'name' => 'Closed after Resolution',
                'request_status_id' => 13,
                'description' => 'The request has been successfully resolved and is now closed.',
            ],
            [
                'name' => 'Closed after Denial',
                'request_status_id' => 13,
                'description' => 'The request was denied and is now closed with no further action needed.',
            ],
            [
                'name' => 'Closed by Requester',
                'request_status_id' => 13,
                'description' => 'The requester voluntarily closed the request before it was processed.',
            ],
            [
                'name' => 'Closed Due to Inactivity',
                'request_status_id' => 13,
                'description' => 'The request was automatically closed due to prolonged inactivity by the requester or beneficiary.',
            ],


        ];

        DB::table('request_sub_statuses')->insert($status);
    }
}
