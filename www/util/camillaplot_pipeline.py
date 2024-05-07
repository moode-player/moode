#!/bin/python3
#
# SPDX-License-Identifier: GPL-3.0-or-later
# Copyright 2014 The moOde audio player project / Tim Curtis
# Copyright 2021 @bitlab (@bitkeeper Git)
#

#
# Generates an image of camilladsp pipeline with camilladsp_plot library
#
import sys
import os
import yaml
from camilladsp_plot.plot_pipeline import plot_pipeline

def main():
    #TODO: not a very safe construction
    pipelinefilename = sys.argv[1]
    pipelinefile = open(pipelinefilename)
    conf = yaml.safe_load(pipelinefile)
    img = plot_pipeline(conf, True)
    with os.fdopen(sys.stdout.fileno(), "wb", closefd=False) as stdout:
        stdout.writelines(img.readlines() )
        stdout.flush()

if __name__ == "__main__":
    main()
