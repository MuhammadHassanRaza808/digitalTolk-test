<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        /** Refactored */
        $response = null;
        if($request->has('user_id')){
            $user_id = $request->get('user_id');
            $response = $this->repository->getUsersJobs($user_id);
        }
        else {
            $user_type = $request->__authenticatedUser->user_type;
            if($user_type == env('ADMIN_ROLE_ID') || $user_type == env('SUPERADMIN_ROLE_ID')){
                $response = $this->repository->getAll($request);
            }
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        /** Refactored */
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->store($user, $data);

        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        /** Refactored */
        $data = $request->except(['_token', 'submit']);
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, $data, $cuser);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        /** Refactored */
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        /** Refactored */
        if($request->has('user_id')) {
            $user_id = $request->get('user_id');
            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        /** Refactored */
        $jobId = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($jobId, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        /** Refactored */
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        /** Refactored */
        $data = $request->all();

        $jobid = null;
        $distance = "";
        $time = "";
        $session = "";
        $flagged = 'no';
        $manually_handled = $data['manually_handled'] == 'true' ? 'yes' : 'no';
        $by_admin = $data['by_admin'] == 'true' ? 'yes' : 'no';
        $admincomment = $data['admincomment'] ?? "";

        if (isset($data['distance']) && $data['distance'] != "") {
            $distance = $data['distance'];
        } 

        if (isset($data['time']) && $data['time'] != "") {
            $time = $data['time'];
        } 

        if (isset($data['jobid']) && $data['jobid'] != "") {
            $jobid = $data['jobid'];
        }

        if (isset($data['session_time']) && $data['session_time'] != "") {
            $session = $data['session_time'];
        } 

        if ($data['flagged'] == 'true') {
            if($admincomment == "") return "Please, add comment";
            $flagged = 'yes';
        }

        if ($jobid && ($time || $distance)) {

            Distance::where('job_id', '=', $jobid)
            ->update(
                array(
                    'distance' => $distance, 
                    'time' => $time
                )
            );
        }

        if ($jobid && ($admincomment || $session || $flagged || $manually_handled || $by_admin)) {

            Job::where('id', '=', $jobid)
            ->update(
                array(
                    'admin_comments' => $admincomment, 
                    'flagged' => $flagged, 
                    'session_time' => $session, 
                    'manually_handled' => $manually_handled, 
                    'by_admin' => $by_admin
                )
            );
        }

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        /** Refactored */
        $jobid = $request->input('jobid');
        $job = $this->repository->find($jobid);
        $this->repository->sendNotificationTranslator($job, $this->repository->jobToData($job), '*');

        return response()->json(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        /** Refactored */
        $jobid = $request->input('jobid');
        $job = $this->repository->find($jobid);
        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response()->json(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response()->json(['success' => $e->getMessage()]);
        }
    }

}
