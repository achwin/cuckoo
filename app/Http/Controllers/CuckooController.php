<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Excel;

class CuckooController extends Controller
{
	protected $jumlahIterasi;
    protected $jumlahSolusi;
    protected $jumlahKota;
    protected $pa;

    public function index()
    {
    	$time_start = microtime(true);
    	// $koordinat = Excel::load('excel/koordinat1.xlsx')->get();
    	// $tsp = [];
    	// foreach ($koordinat as $key => $row) {
    	// 	for ($kota=0; $kota < count($koordinat); $kota++) { 
    	// 			$tsp[(int)$row->no][$kota+1] = sqrt((pow($koordinat[$kota]['x']-$koordinat[$key]['x'], 2))+(pow($koordinat[$kota]['y']-$koordinat[$key]['y'], 2))); 
    	// 		}
    	// }
    	$excelMatrix = Excel::load('excel/kecil2.xlsx')->get();
    	$tsp = [];
    	foreach ($excelMatrix as $key => $row) {
    		for ($i=1; $i <= count($row)-1; $i++) { 
    			$tsp[(int)$row->ij][$i] = $row[$i]; 
    		}
    	}
    	unset($tsp[0]);

    	$excelPengujian = Excel::load('excel/iterasi.xlsx')->get();
    	foreach ($excelPengujian as $key => $row) {
    		$pengujian[(int)$row->no]['iterasi'] = $row['iterasi'];
    		$pengujian[(int)$row->no]['popsize'] = $row['popsize'];
    	}
    	// $this->jumlahIterasi = 10;
    	$this->pa = 0.25;
    	
    	$this->jumlahKota = count($tsp);
    	foreach ($pengujian as $no => $p) {
    		$this->jumlahSolusi = $p['popsize'];
    		$this->jumlahIterasi = $p['iterasi'];
    		// Langkah 1 Generate random
	    	$randomRoutes = $this->randomRoutes($tsp,$this->jumlahSolusi);
	    	// Langkah 2 Pengkodean random routes
	    	$decodedRoutes = $this->decoded($randomRoutes,$this->jumlahSolusi);
	    	// Langkah 3 Add fitness in route
	    	$routesWithFitness = $this->fitnessRoute($tsp,$decodedRoutes,$this->jumlahSolusi);
	    	$bestFitness  = $this->getBestFitness($routesWithFitness);
	    	$best = $bestFitness['decoded']['fitness'];
    		for ($i=1; $i <= $this->jumlahIterasi; $i++) { 
		    	// Langkah 4a Get best and worst fitness
		    	$worstFitness = $this->getWorstFitness($routesWithFitness);
		    	$bestFitness  = $this->getBestFitness($routesWithFitness);
		    	// Langkah 4b Get stepsize
		    	$stepSize = $this->stepSize($routesWithFitness,$worstFitness,$bestFitness,$i);
		    	// Langkah 4c Generate random and make stepsize route
		    	$newRandomRoutes = $this->randomRoutes($tsp, $this->jumlahSolusi);
		    	$stepSizeRoutes = $this->stepSizeRoutes($randomRoutes,$newRandomRoutes,$stepSize);
		    	$decodedStepSizeRoutes = $this->decoded($stepSizeRoutes,$this->jumlahSolusi);
		    	$stepSizeRoutesWithFitness = $this->fitnessRoute($tsp,$decodedStepSizeRoutes,$this->jumlahSolusi);
		    	$comparedRoutes = $this->compareFitness($routesWithFitness,$stepSizeRoutesWithFitness);
		    	if ((mt_rand() / mt_getrandmax()) > $this->pa) {
		    		$worstFitnessComparedRoutes = $this->getWorstFitness($comparedRoutes);
		    		$worstPosition = array_search($worstFitnessComparedRoutes, $comparedRoutes);
		    		$newRoute = $this->randomRoutes($tsp,1);
		    		$decodedNewRoute = $this->decoded($newRoute,1);
		    		$newRouteWithFitness = $this->fitnessRoute($tsp,$decodedNewRoute,1);
		    		$comparedRoutes[$worstPosition] = $newRouteWithFitness['rute1'];
		    	}
		    	$bestFitnessComparedRoutes = $this->getBestFitness($comparedRoutes);
		    	$bestFitnessPosition = array_search($bestFitnessComparedRoutes, $comparedRoutes);
		    	$bestRoute['Iterasi'.$i][$bestFitnessPosition] = $bestFitnessComparedRoutes;
		    	if ($best > $bestRoute['Iterasi'.$i][$bestFitnessPosition]['decoded']['fitness']) {
		    		$best = $bestRoute['Iterasi'.$i][$bestFitnessPosition]['decoded']['fitness'];
		    		$optimalRoute = $bestRoute['Iterasi'.$i][$bestFitnessPosition]['decoded'];
		    	}
		    	$optimalRoute = $bestRoute['Iterasi'.$i][$bestFitnessPosition]['decoded'];
		    	$routesWithFitness = $comparedRoutes;
		    	// var_dump($best);
		    }
		    $results['Iterasi '.$p['iterasi'].' Popsize '.$p['popsize']]['rute'] = $optimalRoute;
		    $results['Iterasi '.$p['iterasi'].' Popsize '.$p['popsize']]['optimal'] = $optimalRoute['fitness'];
    	}
    	\Excel::create('Cukcoo ('.date('d-m-Y').')', function($excel) use($results){
                $excel->sheet('sheet', function($sheet) use($results){
                    $data = array();
                    $no = 0;
                    foreach ($results as $key => $result) {
                    	unset($result['rute']['fitness']);
                        $data[] = array(
                            ++$no,
                            $key,
                            $result['optimal'],
                            implode(",", $result['rute'])
                        );
                    }
                    $sheet->fromArray($data, null, 'A1', false, false);
                    $headings = array('No','Pengujian','Optimal','Rute');
                    $sheet->prependRow(1, $headings);
                });
            })->export('xlsx');
	    // dd($best,(microtime(true) - $time_start.' detik'));
    }

    public function randomRoutes($tsp, $jumlahSolusi)
    {
    	$rute = [];
    	for ($i=1; $i <= $jumlahSolusi; $i++) { 
    		// Mencari random tiap rute
    		for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
    			$rute['rute'.$i]['random_keys'][$kota] = 1 + ($this->jumlahKota - 1) * (mt_rand() / mt_getrandmax());
    		}
    	}
    	return $rute;
    }

    public function fitnessRoute($tsp,$routes,$jumlahSolusi)
    {
    	$routesWithFitness = [];
    	// $routes = [
    	// 	'rute1' => [
    	// 		1 => 3,
    	// 		2 => 4,
    	// 		3 => 2,
    	// 		4 => 1,
    	// 		5 => 5,
    	// 	],
    	// 	'rute2' => [
    	// 		1 => 3,
    	// 		2 => 4,
    	// 		3 => 5,
    	// 		4 => 2,
    	// 		5 => 1,
    	// 	],
    	// 	'rute3' => [
    	// 		1 => 2,
    	// 		2 => 5,
    	// 		3 => 4,
    	// 		4 => 3,
    	// 		5 => 1,
    	// 	],
    	// 	'rute4' => [
    	// 		1 => 1,
    	// 		2 => 5,
    	// 		3 => 3,
    	// 		4 => 2,
    	// 		5 => 4,
    	// 	],
    	// ];
    	for ($solusi=1; $solusi <= $jumlahSolusi; $solusi++) {
    		$sum = 0;
    		for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
    			if ($kota < $this->jumlahKota) {
    				$routesWithFitness['rute'.$solusi]['decoded'][$kota] = 
    				$tsp[$routes['rute'.$solusi]['decoded'][$kota]][$routes['rute'.$solusi]['decoded'][$kota+1]];
    			}
    			elseif($kota == $this->jumlahKota){
    				$routesWithFitness['rute'.$solusi]['decoded'][$kota] = 
    				$tsp[$routes['rute'.$solusi]['decoded'][$kota]][$routes['rute'.$solusi]['decoded'][1]];
    			}
    			$sum = $sum + $routesWithFitness['rute'.$solusi]['decoded'][$kota];
    		}
    		$routes['rute'.$solusi]['decoded']['fitness'] = $sum;
    	}
    	// dd($routes);
    	return $routes;
    }

    public function getWorstFitness($routesWithFitness)
    {
    	usort($routesWithFitness, function($a, $b) {
            return $a['decoded']['fitness'] < $b['decoded']['fitness'];
        });
        return $routesWithFitness[0];
    }

    public function getBestFitness($routesWithFitness)
    {
    	usort($routesWithFitness, function($a, $b) {
            return $a['decoded']['fitness'] > $b['decoded']['fitness'];
        });
        return $routesWithFitness[0];
    }

    public function stepSize($routesWithFitness,$worstFitness,$bestFitness,$iterasi)
    {
    	for ($i=1; $i <= $this->jumlahSolusi; $i++) { 
    		if (($bestFitness['decoded']['fitness'] - $worstFitness['decoded']['fitness']) == 0) {
    			$pembagi = mt_rand() / mt_getrandmax();
    		}
    		else{
    			$pembagi = $bestFitness['decoded']['fitness']-$worstFitness['decoded']['fitness'];
    		}
    		$step[$i] = pow((1/$iterasi), 
    			abs(($bestFitness['decoded']['fitness']-$routesWithFitness['rute'.$i]['decoded']['fitness']) / $pembagi
    			));
    	}
    	return $step;
    }

    public function decoded($routes,$jumlahSolusi)
    {
	    for ($i=1; $i <= $jumlahSolusi; $i++) { 
			// Mencari random tiap rute
			for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
				$rank['rute'.$i][$kota] = $routes['rute'.$i]['random_keys'][$kota];
			}
			// Mencari rank
			sort($rank['rute'.$i]);
			for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
				$routes['rute'.$i]['decoded'][$kota] = array_search($routes['rute'.$i]['random_keys'][$kota], $rank['rute'.$i]) + 1;
				}
    	}
    	return $routes;
    }

    public function stepSizeRoutes($routes,$newRoutes,$stepSize)
    {
    	for ($rute=1; $rute <= $this->jumlahSolusi; $rute++) { 
    		for ($kota=1; $kota <= $this->jumlahKota; $kota++) { 
    			$stepSizeRoutes['rute'.$rute]['random_keys'][$kota] = 
    			$routes['rute'.$rute]['random_keys'][$kota] + $newRoutes['rute'.$rute]['random_keys'][$kota] * $stepSize[$rute];
    		}
    	}
    	return $stepSizeRoutes;
    }

    public function compareFitness($routesWithFitness,$stepSizeRoutesWithFitness)
    {
    	for ($rute=1; $rute <= $this->jumlahSolusi; $rute++) {
    		$random = rand(1,$this->jumlahSolusi);
    		if ($routesWithFitness['rute'.$rute]['decoded']['fitness'] > $stepSizeRoutesWithFitness['rute'.$random]['decoded']['fitness']) {
    			$comparedRoutes['rute'.$rute] = $stepSizeRoutesWithFitness['rute'.$random];
    		}
    		else{
    			$comparedRoutes['rute'.$rute] = $routesWithFitness['rute'.$rute];
    		}
    	}
    	return $comparedRoutes;
    }

    public function encoded($routes,$jumlahSolusi)
    {
    	
    }
}
